<?php
declare(strict_types=1);

// Password reset form
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\AuthController())->resetPassword();
