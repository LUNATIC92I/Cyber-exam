<?php
declare(strict_types=1);

// Checkout, payment status and local sandbox
require __DIR__ . '/includes/bootstrap.php';
(new App\Controllers\CheckoutController())->handle();
