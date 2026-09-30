<?php
declare(strict_types=1);

// Exam room / exam start
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\ExamController())->handle();
