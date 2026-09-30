<?php
declare(strict_types=1);

// Public certificate page
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\CertificateController())->show();
