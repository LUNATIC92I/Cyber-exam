<?php
declare(strict_types=1);

// Candidate sign-in
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\AuthController())->login();
