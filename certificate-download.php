<?php
declare(strict_types=1);

// Certificate PDF (owner / staff only)
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\CertificateController())->download();
