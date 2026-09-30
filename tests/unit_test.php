<?php
declare(strict_types=1);

/**
 * CYBER EXAM — unit tests for core logic (no HTTP server required).
 *   php tests/unit_test.php
 * Uses the configured database for the scoring/certificate integration checks.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
define('CEX_TESTING', true);
require dirname(__DIR__) . '/includes/bootstrap.php';

use App\Core\Database;
use App\Core\Security;
use App\Models\Certificate;
use App\Services\CertificatePdf;
use App\Services\CertificateService;
use App\Services\Payment\StripeSignature;
use App\Services\Payment\WebhookException;
use App\Services\QrCode;
use App\Services\ScoringService;

$pass = 0;
$fail = 0;
function t(string $label, bool $cond): void
{
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    echo ($cond ? "  \033[32mPASS\033[0m " : "  \033[31mFAIL\033[0m ") . $label . PHP_EOL;
}

echo "Scoring (pass threshold = 70%)\n";
// Boundary cases on a 100-question exam.
t('69/100 → 69% NOT PASSED', ScoringService::evaluate(69, 100, 70) === ['correct' => 69, 'incorrect' => 31, 'total' => 100, 'percent' => 69.0, 'passed' => false]);
t('70/100 → 70% PASSED', ScoringService::evaluate(70, 100, 70)['passed'] === true);
t('71/100 → 71% PASSED', ScoringService::evaluate(71, 100, 70)['passed'] === true);
$r69 = ScoringService::evaluate(69, 100, 70);
t('69% percent value correct', $r69['percent'] === 69.0 && $r69['incorrect'] === 31);
// Non-round totals: integer comparison avoids float boundary errors.
t('7/10 → 70% PASSED (exact boundary, no float error)', ScoringService::evaluate(7, 10, 70)['passed'] === true);
t('6/10 → 60% NOT PASSED', ScoringService::evaluate(6, 10, 70)['passed'] === false);
t('34/49 → 69.39% NOT PASSED', ScoringService::evaluate(34, 49, 70)['passed'] === false && ScoringService::evaluate(34, 49, 70)['percent'] === 69.39);
t('0 questions → not passed, no divide-by-zero', ScoringService::evaluate(0, 0, 70)['passed'] === false);
t('correct capped at total', ScoringService::evaluate(150, 100, 70)['correct'] === 100);

echo "\nStripe-style webhook signature\n";
$payload = '{"id":"evt_1","type":"checkout.session.completed"}';
$secret = 'whsec_test_secret';
$good = StripeSignature::header($payload, $secret);
$okSig = true;
try {
    StripeSignature::verify($payload, $good, $secret);
} catch (WebhookException) {
    $okSig = false;
}
t('valid signature accepted', $okSig);
t('tampered payload rejected', (function () use ($good, $secret) {
    try { StripeSignature::verify('{"id":"evt_evil"}', $good, $secret); return false; } catch (WebhookException) { return true; }
})());
t('wrong secret rejected', (function () use ($payload, $good) {
    try { StripeSignature::verify($payload, $good, 'whsec_other'); return false; } catch (WebhookException) { return true; }
})());
t('old timestamp outside tolerance rejected', (function () use ($payload, $secret) {
    $old = StripeSignature::header($payload, $secret, time() - 10000);
    try { StripeSignature::verify($payload, $old, $secret); return false; } catch (WebhookException) { return true; }
})());
t('malformed header rejected', (function () use ($payload, $secret) {
    try { StripeSignature::verify($payload, 'garbage', $secret); return false; } catch (WebhookException) { return true; }
})());

echo "\nQR code (verification link)\n";
$code = 'CEX-SOC-2026-7KQ2M9XA';
$qr = new QrCode('https://example.com/verify.php?id=' . $code);
t('QR matrix is square and non-trivial', count($qr->matrix()) === $qr->size && $qr->size >= 21);
t('QR SVG renders', str_contains($qr->toSvg(), '<svg'));

echo "\nCertificate code format\n";
t('generated code matches format', Certificate::isValidFormat(CertificateService::generateCode('SOC', 2026)));
t('code has no ambiguous characters', !preg_match('/[01OI]/', substr(CertificateService::generateCode('ARCH', 2026), 13)));
t('invalid code rejected', !Certificate::isValidFormat('NOT-A-CODE'));

echo "\nCertificate signature (tamper evidence)\n";
$certData = ['certificate_code' => $code, 'user_id' => 1, 'attempt_id' => 1, 'certification_id' => 1,
    'candidate_name' => 'Test User', 'certification_name' => 'SOC Analyst', 'score_percent' => '87.00', 'issued_at' => '2026-09-30 10:00:00'];
$certData['signature'] = CertificateService::sign($certData);
t('valid signature verifies', CertificateService::hasValidSignature($certData));
$tampered = $certData;
$tampered['score_percent'] = '99.00';
t('tampered score fails signature', !CertificateService::hasValidSignature($tampered));
$tampered2 = $certData;
$tampered2['candidate_name'] = 'Someone Else';
t('tampered name fails signature', !CertificateService::hasValidSignature($tampered2));

echo "\nCertificate PDF generation\n";
$pdf = (new CertificatePdf())->render($certData + ['status' => 'valid']);
t('PDF has valid header', str_starts_with($pdf, '%PDF-1.'));
t('PDF has EOF marker', str_contains($pdf, '%%EOF'));
t('PDF is non-trivial size', strlen($pdf) > 3000);

echo "\nPassword hashing\n";
$hash = password_hash('Str0ng!Passw0rd', PASSWORD_DEFAULT);
t('password verifies', password_verify('Str0ng!Passw0rd', $hash));
t('wrong password fails', !password_verify('wrong', $hash));
t('hash is not plaintext', !str_contains($hash, 'Str0ng'));

echo "\nHMAC / app key\n";
t('hmac is deterministic', Security::hmac('abc') === Security::hmac('abc'));
t('hmac differs by input', Security::hmac('abc') !== Security::hmac('abd'));

echo "\nQuestion banks (content integrity)\n";
require dirname(__DIR__) . '/seed.php';
[$certs, $errors] = cex_load_banks();
t('no validation errors in banks', $errors === []);
t('seven certifications defined', count($certs) === 7);
$totalQ = array_sum(array_map(fn ($c) => count($c['questions']), $certs));
t('at least 700 questions total', $totalQ >= 700);
$allComplete = true;
foreach ($certs as $c) {
    if (count($c['questions']) < 100) {
        $allComplete = false;
    }
}
t('every certification has >= 100 questions', $allComplete);
// Answer distribution should not be trivially skewed (options are also shuffled per exam).
$balanced = true;
foreach ($certs as $c) {
    $dist = array_count_values(array_column($c['questions'], 'correct_option'));
    foreach (['A', 'B', 'C', 'D'] as $L) {
        if (($dist[$L] ?? 0) < 5) {
            $balanced = false;
        }
    }
}
t('answer key reasonably balanced across A-D', $balanced);

echo "\n" . ($fail === 0 ? "\033[32m" : "\033[31m") . "Result: {$pass} passed, {$fail} failed\033[0m\n";
exit($fail ? 1 : 0);
