<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
(new App\Controllers\Admin\AdminController())->questions();
