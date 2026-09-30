<?php
declare(strict_types=1);

// Certification catalogue
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\PageController())->certifications();
