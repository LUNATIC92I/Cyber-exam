<?php
declare(strict_types=1);

// Exam result
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\ResultController())->show();
