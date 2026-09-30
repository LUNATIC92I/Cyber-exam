<?php
declare(strict_types=1);

// Payment status polling (read-only)
require __DIR__ . '/../includes/bootstrap.php';
(new App\Controllers\CheckoutController())->statusApi();
