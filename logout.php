<?php
declare(strict_types=1);

// Sign-out (POST + CSRF only)
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\AuthController())->logout();
