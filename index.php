<?php
declare(strict_types=1);

// Landing page
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\PageController())->home();
