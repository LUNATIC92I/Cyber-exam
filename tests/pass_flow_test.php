<?php
declare(strict_types=1);

/**
 * End-to-end PASS path: registers a candidate, pays (sandbox), starts an exam,
 * answers every question CORRECTLY using the answer key from the database
 * (test-only), submits, and verifies certificate issuance, PDF download, public
 * verification and revocation.
 *
 *   php -S 127.0.0.1:8000 tools/dev-router.php   (APP_ENV=local, sandbox, mail=log)
 *   php tests/pass_flow_test.php http://localhost:8000
 *
 * Requires DB access from the CLI to read the answer key (this is a test harness,
 * not part of the application runtime).
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
require dirname(__DIR__) . '/includes/bootstrap.php';

use App\Core\Database;
use App\Services\CertificateService;

$jar = tempnam(sys_get_temp_dir(), 'cexpass');
$pass = 0;
$fail = 0;
function ok(bool $c, string $l): void
{
    global $pass, $fail;
    $c ? $pass++ : $fail++;
    echo ($c ? "  \033[32m✔\033[0m " : "  \033[31m✘\033[0m ") . $l . PHP_EOL;
}
function req(string $method, string $url, array $data = [], array $headers = [], bool $json = false): array
{
    global $jar;
    $ch = curl_init($url);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers];
    if ($method === 'POST') {
        $o[CURLOPT_POSTFIELDS] = $json ? json_encode($data) : http_build_query($data);
    }
    curl_setopt_array($ch, $o);
    $raw = (string) curl_exec($ch);
    $sz = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $st = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $head = substr($raw, 0, $sz);
    preg_match('/^Location:\s*(.+)$/mi', $head, $m);
    return ['status' => $st, 'headers' => $head, 'body' => substr($raw, $sz), 'location' => trim($m[1] ?? '')];
}
function tok(string $html): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) || preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

echo "CYBER EXAM — full PASS path against {$base}\n";
$email = 'pass' . bin2hex(random_bytes(4)) . '@example.com';
$pw = 'Str0ng!Passw0rd';

// Register + verify email directly in DB (verification flow itself is covered by http_flow_test).
$reg = req('GET', "$base/register.php");
$r = req('POST', "$base/register.php", ['_csrf' => tok($reg['body']), 'first_name' => 'Grace', 'last_name' => 'Achebe',
    'email' => $email, 'country' => 'FR', 'password' => $pw, 'password_confirmation' => $pw, 'terms' => '1']);
ok($r['status'] === 303, 'candidate registered');
Database::query('UPDATE users SET email_verified_at = :n WHERE email = :e', ['n' => Database::now(), 'e' => $email]);

$dash = req('GET', "$base/dashboard.php");
$csrf = tok($dash['body']);
preg_match('/name="certification_id" value="(\d+)"/', req('GET', "$base/checkout.php?certification=soc-analyst")['body'], $m);
$certId = $m[1];

// Pay via sandbox signed webhook.
$c = req('POST', "$base/checkout.php", ['_csrf' => $csrf, 'action' => 'create', 'certification_id' => $certId]);
preg_match('/sandbox=([0-9a-f-]{36})/', $c['location'], $m);
$sbx = req('GET', "$base/checkout.php?sandbox={$m[1]}");
req('POST', "$base/checkout.php", ['_csrf' => tok($sbx['body']), 'action' => 'sandbox', 'payment' => $m[1], 'outcome' => 'pay']);
ok((int) Database::value('SELECT COUNT(*) FROM payments WHERE status = "paid" AND user_id = (SELECT id FROM users WHERE email = :e)', ['e' => $email]) === 1, 'payment confirmed via webhook');

// Start exam.
$s = req('POST', "$base/exam.php", ['_csrf' => $csrf, 'certification_id' => $certId]);
preg_match('/attempt=([0-9a-f-]{36})/', $s['location'], $m);
$attempt = $m[1];
$examPage = req('GET', "$base/exam.php?attempt=$attempt");
$apiTok = tok($examPage['body']);
$hdr = ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $apiTok];

// Read the answer key (test-only) and map each displayed slot to the correct letter.
$attemptRow = Database::fetch('SELECT id FROM exam_attempts WHERE uuid = :u', ['u' => $attempt]);
$rows = Database::fetchAll(
    'SELECT eq.position, eq.option_order, q.correct_option FROM exam_questions eq JOIN questions q ON q.id = eq.question_id WHERE eq.attempt_id = :a',
    ['a' => $attemptRow['id']]
);
ok(count($rows) === 100, 'attempt has 100 questions');
$allSaved = true;
foreach ($rows as $row) {
    // option_order maps displayed slot (index) -> original letter; find the slot showing the correct option.
    $slot = strpos($row['option_order'], $row['correct_option']);
    $letter = chr(65 + $slot);
    $res = json_decode(req('POST', "$base/api/exam.php?action=answer",
        ['attempt' => $attempt, 'position' => (int) $row['position'], 'choice' => $letter], $hdr, true)['body'], true);
    $allSaved = $allSaved && !empty($res['ok']);
}
ok($allSaved, 'all 100 correct answers saved');

$submit = json_decode(req('POST', "$base/api/exam.php?action=submit", ['attempt' => $attempt], $hdr, true)['body'], true);
ok(!empty($submit['redirect']), 'exam submitted');

$final = Database::fetch('SELECT * FROM exam_attempts WHERE uuid = :u', ['u' => $attempt]);
ok((float) $final['score_percent'] === 100.0, 'score is 100%');
ok((int) $final['passed'] === 1, 'attempt marked PASSED');

$cert = Database::fetch('SELECT * FROM certificates WHERE attempt_id = :a', ['a' => $final['id']]);
ok($cert !== null, 'certificate issued automatically');
ok(CertificateService::hasValidSignature($cert), 'certificate signature is valid');

$result = req('GET', "$base/result.php?attempt=$attempt");
ok(str_contains($result['body'], 'PASSED') && str_contains($result['body'], $cert['certificate_code']), 'result page shows PASSED and certificate ID');

// Confirmation emails.
$mails = implode(' ', array_map('file_get_contents', glob(dirname(__DIR__) . '/storage/mail/*.txt')));
ok(str_contains($mails, 'Congratulations') && str_contains($mails, $email), 'pass email queued');
ok(str_contains($mails, 'certificate is ready'), 'certificate email queued');

// Certificate PDF download (owner).
$dl = req('GET', "$base/certificate-download.php?id={$cert['certificate_code']}");
ok($dl['status'] === 200 && str_contains($dl['body'], '%PDF-1.'), 'owner can download certificate PDF');

// Public verification.
$v = req('GET', "$base/verify.php?id={$cert['certificate_code']}");
ok(str_contains($v['body'], 'VERIFIED') && str_contains($v['body'], 'Grace Achebe'), 'public verification shows VERIFIED');
$cpage = req('GET', "$base/certificate.php?id={$cert['certificate_code']}");
ok($cpage['status'] === 200 && str_contains($cpage['body'], 'CERTIFICATE OF ACHIEVEMENT'), 'public certificate page renders');

// URL tampering must not verify.
$bad = req('GET', "$base/verify.php?id=CEX-SOC-2026-AAAAAAAA");
ok(!str_contains($bad['body'], 'VERIFIED') || str_contains($bad['body'], 'NOT FOUND'), 'tampered certificate ID does not verify');

// Revoke via admin and confirm public status flips.
CertificateService::revoke($cert['certificate_code'], 'Automated test revocation');
$vr = req('GET', "$base/verify.php?id={$cert['certificate_code']}");
ok(str_contains($vr['body'], 'REVOKED'), 'revoked certificate shows REVOKED publicly');

@unlink($jar);
echo "\n" . ($fail === 0 ? "\033[32m" : "\033[31m") . "Result: {$pass} passed, {$fail} failed\033[0m\n";
exit($fail ? 1 : 0);
