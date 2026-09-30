<?php
declare(strict_types=1);

// Candidate registration
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\AuthController())->register();
