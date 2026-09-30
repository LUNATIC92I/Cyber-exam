<?php
declare(strict_types=1);

// Exam room JSON API
require __DIR__ . '/../includes/bootstrap.php';
(new App\Controllers\ExamController())->api();
