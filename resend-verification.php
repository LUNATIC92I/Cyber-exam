<?php
declare(strict_types=1);

// Resend the email confirmation link
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\AuthController())->resendVerification();
