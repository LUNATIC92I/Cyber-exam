<?php
declare(strict_types=1);

// Stripe webhook endpoint
require __DIR__ . '/../../includes/bootstrap.php';
(new App\Controllers\WebhookController())->handle('stripe');
