<?php
declare(strict_types=1);

// Candidate dashboard
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\DashboardController())->index();
