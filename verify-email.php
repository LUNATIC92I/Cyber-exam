<?php
declare(strict_types=1);

// Email confirmation link target
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\AuthController())->verifyEmail();
