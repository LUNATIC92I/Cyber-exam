<?php
declare(strict_types=1);

// Local sandbox webhook endpoint (disabled in production)
require __DIR__ . '/../../includes/bootstrap.php';
(new App\Controllers\WebhookController())->handle('sandbox');
