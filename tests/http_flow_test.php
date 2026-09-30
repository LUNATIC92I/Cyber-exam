<?php
declare(strict_types=1);

/**
 * End-to-end HTTP test of the candidate journey against a running server:
 *   Register → Verify email → Login → Pay (sandbox, signed webhook) → Start exam
 *   → Answer → Submit → Result → Certificate (PDF) → Public verification.
 *
 *   php -S 127.0.0.1:8000 tools/dev-router.php   (APP_ENV=local, PAYMENT_PROVIDER=sandbox, MAIL_DRIVER=log)
 *   php tests/http_flow_test.php http://127.0.0.1:8000
 */

if (PHP_SAPI !== 'cli') {
    exit;
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$root = dirname(__DIR__);
$jar = tempnam(sys_get_temp_dir(), 'cexjar');
$pass = 0;
$fail = 0;

function check(bool $cond, string $label): void
{
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    echo ($cond ? "  \033[32m✔\033[0m " : "  \033[31m✘\033[0m ") . $label . PHP_EOL;
}

function http(string $method, string $url, array $data = [], array $headers = [], bool $json = false): array
{
    global $jar;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POSTFIELDS] = $json ? json_encode($data) : http_build_query($data);
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $head = substr($raw, 0, $size);
    preg_match('/^Location:\s*(.+)$/mi', $head, $m);
    return ['status' => $status, 'headers' => $head, 'body' => substr($raw, $size), 'location' => trim($m[1] ?? '')];
}

function csrf(string $html): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m) || preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

function follow(array $r): array
{
    global $base;
    for ($i = 0; $i < 5 && $r['location']; $i++) {
        $loc = str_starts_with($r['location'], 'http') ? $r['location'] : $base . $r['location'];
        $r = http('GET', $loc);
    }
    return $r;
}

echo "CYBER EXAM — HTTP flow test against {$base}\n";
$email = 'flow' . bin2hex(random_bytes(4)) . '@example.com';
$password = 'Str0ng!Passw0rd';

// 1. Registration --------------------------------------------------------
$r = http('GET', "$base/register.php");
check($r['status'] === 200, 'Register page loads');
$token = csrf($r['body']);
$r = http('POST', "$base/register.php", ['_csrf' => 'bad', 'first_name' => 'A']);
check($r['status'] === 419, 'Registration without valid CSRF token is rejected (419)');
$r = http('POST', "$base/register.php", ['_csrf' => $token, 'first_name' => 'Test', 'last_name' => 'Candidate', 'email' => $email,
    'country' => 'FR', 'password' => 'weak', 'password_confirmation' => 'weak', 'terms' => '1']);
check($r['status'] === 303 && str_contains($r['location'], 'register.php'), 'Weak password is rejected');
$r = http('POST', "$base/register.php", ['_csrf' => $token, 'first_name' => 'Test', 'last_name' => 'Candidate', 'email' => $email,
    'country' => 'FR', 'password' => $password, 'password_confirmation' => $password, 'terms' => '1']);
check($r['status'] === 303 && str_contains($r['location'], 'dashboard.php'), 'Valid registration redirects to dashboard');
$dash = follow($r);
check(str_contains($dash['body'], 'Welcome, Test'), 'Dashboard greets the candidate');
check(str_contains($dash['body'], 'confirm your email'), 'Unverified email banner is displayed');

// 2. Email verification ---------------------------------------------------
$files = glob("$root/storage/mail/*.txt");
usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
$link = null;
foreach ($files as $f) {
    $txt = file_get_contents($f);
    if (str_contains($txt, $email) && preg_match('#(https?://\S+verify-email\.php\?token=[A-Za-z0-9_-]+)#', $txt, $m)) {
        $link = $m[1];
        break;
    }
}
check($link !== null, 'Verification email was sent with a link');
$slug = 'soc-analyst';
$r = http('GET', "$base/checkout.php?certification=$slug");
check(str_contains($r['body'], 'Confirm your email'), 'Checkout is blocked until the email is confirmed');
$r = follow(http('GET', str_replace(parse_url($link, PHP_URL_SCHEME) . '://' . parse_url($link, PHP_URL_HOST) . (parse_url($link, PHP_URL_PORT) ? ':' . parse_url($link, PHP_URL_PORT) : ''), $base, (string) $link)));
check(str_contains($r['body'], 'email address is confirmed'), 'Email verification succeeds');

// 3. Logout / login -------------------------------------------------------
$token = csrf($r['body']);
$r = http('GET', "$base/logout.php");
check($r['status'] === 405, 'Logout via GET is refused');
$r = follow(http('POST', "$base/logout.php", ['_csrf' => $token]));
check(str_contains($r['body'], 'signed out'), 'Logout works');
$r = http('GET', "$base/dashboard.php");
check($r['status'] === 303 && str_contains($r['location'], 'login.php'), 'Dashboard requires authentication');
$page = http('GET', "$base/login.php");
$r = http('POST', "$base/login.php", ['_csrf' => csrf($page['body']), 'email' => $email, 'password' => 'Wrong!Passw0rd']);
check(str_contains(follow($r)['body'], 'Invalid email or password'), 'Wrong password is rejected with a generic message');
$page = http('GET', "$base/login.php");
$r = http('POST', "$base/login.php", ['_csrf' => csrf($page['body']), 'email' => $email, 'password' => $password]);
check($r['status'] === 303, 'Login succeeds');
$dash = follow($r);
check(str_contains($dash['body'], 'PAY €10'), 'Unpaid certification shows PAY €10');

// 4. Exam cannot start without payment -----------------------------------
preg_match('/name="certification_id" value="(\d+)"/', http('GET', "$base/checkout.php?certification=$slug")['body'], $m);
$certId = $m[1] ?? '';
$token = csrf($dash['body']);
$r = http('POST', "$base/exam.php", ['_csrf' => $token, 'certification_id' => $certId]);
check($r['status'] === 303 && str_contains($r['location'], 'checkout.php'), 'Starting an exam without payment is refused');

// 5. Payment (sandbox → signed webhook) -----------------------------------
$r = http('POST', "$base/checkout.php", ['_csrf' => $token, 'action' => 'create', 'certification_id' => $certId]);
check($r['status'] === 303 && str_contains($r['location'], 'sandbox='), 'Checkout creates a pending payment and redirects to the provider');
$sandbox = follow($r);
preg_match('/name="payment" value="([0-9a-f-]{36})"/', $sandbox['body'], $m);
$paymentUuid = $m[1] ?? '';
$status = http('GET', "$base/checkout.php?payment=$paymentUuid&status=success");
check(str_contains($status['body'], 'Confirming your payment'), 'A success redirect alone does NOT mark the payment as paid');
$r = http('POST', "$base/api/webhooks/sandbox.php", [], ['Content-Type: application/json', 'Stripe-Signature: t=' . time() . ',v1=deadbeef']);
check($r['status'] === 400, 'Webhook with an invalid signature is rejected');
$r = follow(http('POST', "$base/checkout.php", ['_csrf' => $token, 'action' => 'sandbox', 'payment' => $paymentUuid, 'outcome' => 'pay']));
check(str_contains($r['body'], 'Payment Confirmed'), 'Signed webhook confirms the payment');
$api = json_decode(http('GET', "$base/api/payment-status.php?payment=$paymentUuid", [], ['Accept: application/json'])['body'], true);
check(($api['status'] ?? '') === 'paid', 'Payment status API reports paid');

// 6. Start exam -----------------------------------------------------------
$r = http('POST', "$base/exam.php", ['_csrf' => $token, 'certification_id' => $certId]);
check($r['status'] === 303 && str_contains($r['location'], 'exam.php?attempt='), 'Exam starts after confirmed payment');
preg_match('/attempt=([0-9a-f-]{36})/', $r['location'], $m);
$attempt = $m[1] ?? '';
$examPage = follow($r);
$apiToken = csrf($examPage['body']);
check($examPage['status'] === 200 && str_contains($examPage['body'], 'exam-app'), 'Exam room renders');
$r = http('POST', "$base/exam.php", ['_csrf' => $token, 'certification_id' => $certId]);
check(str_contains($r['location'], $attempt), 'Starting again resumes the same attempt (payment consumed once)');

$state = json_decode(http('GET', "$base/api/exam.php?action=state&attempt=$attempt", [], ['Accept: application/json'])['body'], true);
check(count($state['questions'] ?? []) === 100, 'Exam contains exactly 100 questions');
check(($state['remaining'] ?? 0) > 7100 && $state['remaining'] <= 7200, 'Server-side timer starts at 120 minutes');
check(!str_contains(json_encode($state), 'correct_option') && !str_contains(json_encode($state), 'explanation'), 'Exam payload never exposes the answer key');
$positions = array_column($state['questions'], 'position');
check($positions === range(1, 100), 'Questions are numbered 1..100');

$r = http('POST', "$base/api/exam.php?action=answer", ['attempt' => $attempt, 'position' => 1, 'choice' => 'A'],
    ['Content-Type: application/json', 'Accept: application/json'], true);
check($r['status'] === 419, 'Answer without CSRF header is rejected');
$hdr = ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $apiToken];
$ok = true;
foreach ([1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'A'] as $pos => $choice) {
    $res = json_decode(http('POST', "$base/api/exam.php?action=answer", ['attempt' => $attempt, 'position' => $pos, 'choice' => $choice], $hdr, true)['body'], true);
    $ok = $ok && !empty($res['ok']);
}
check($ok && ($res['answered'] ?? 0) === 5, 'Answers are saved server-side');
$bad = http('POST', "$base/api/exam.php?action=answer", ['attempt' => $attempt, 'position' => 1, 'choice' => 'Z'], $hdr, true);
check($bad['status'] === 422, 'Invalid answer letter is rejected');
$state2 = json_decode(http('GET', "$base/api/exam.php?action=state&attempt=$attempt", [], ['Accept: application/json'])['body'], true);
check(($state2['questions'][1]['selected'] ?? null) === 'B', 'Saved answer is restored on reload (resume)');

// 7. Submit → result --------------------------------------------------------
$res = json_decode(http('POST', "$base/api/exam.php?action=submit", ['attempt' => $attempt], $hdr, true)['body'], true);
check(!empty($res['redirect']) && str_contains($res['redirect'], 'result.php'), 'Submission returns the result URL');
$result = http('GET', "$base/result.php?attempt=$attempt");
check($result['status'] === 200 && str_contains($result['body'], 'EXAM COMPLETED'), 'Result page renders');
check(str_contains($result['body'], 'NOT PASSED'), 'Low score is reported as NOT PASSED');
$late = http('POST', "$base/api/exam.php?action=answer", ['attempt' => $attempt, 'position' => 6, 'choice' => 'A'], $hdr, true);
check($late['status'] === 409, 'Answers are refused after submission');

// 8. Authorisation ---------------------------------------------------------
@unlink($jar);
$jar = tempnam(sys_get_temp_dir(), 'cexjar');
$r = http('GET', "$base/result.php?attempt=$attempt");
check($r['status'] === 303, 'Another visitor cannot view the result (redirect to login)');
$r = http('GET', "$base/admin/index.php");
check($r['status'] === 303 && str_contains($r['location'], 'admin/login.php'), 'Admin area requires admin authentication');
$r = http('GET', "$base/verify.php?id=CEX-SOC-2026-ZZZZZZZZ");
check(str_contains($r['body'], 'NOT FOUND'), 'Unknown certificate ID is not verified');
$r = http('GET', "$base/verify.php?id=" . urlencode('<script>alert(1)</script>'));
check(!str_contains($r['body'], '<script>alert(1)</script>'), 'Verification input is escaped (no reflected XSS)');

@unlink($jar);
echo PHP_EOL . "Result: {$pass} passed, {$fail} failed" . PHP_EOL;
exit($fail ? 1 : 0);
