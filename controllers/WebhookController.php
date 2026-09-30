<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SandboxGateway;
use App\Services\Payment\WebhookException;

/**
 * Inbound payment webhooks. Authenticated exclusively by the provider
 * signature (no session, no CSRF token — it is a server-to-server call).
 */
final class WebhookController
{
    public function handle(string $provider): never
    {
        if (Request::method() !== 'POST') {
            Response::json(['error' => 'Method not allowed'], 405);
        }
        if ($provider === 'sandbox' && SandboxGateway::disabled()) {
            Response::json(['error' => 'Not found'], 404);
        }
        $payload = (string) file_get_contents('php://input');
        if (strlen($payload) > 256 * 1024) {
            Response::json(['error' => 'Payload too large'], 413);
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($k, 5)))] = $v;
            }
        }
        try {
            $status = PaymentService::handleWebhook($provider, $payload, $headers);
            Response::json(['received' => true, 'result' => $status]);
        } catch (WebhookException $e) {
            Audit::log('payment.webhook_rejected', 'webhook', null, ['provider' => $provider, 'reason' => $e->getMessage()], 'system');
            // Unknown payment: acknowledge so the provider stops retrying; bad signature: 400.
            $code = str_starts_with($e->getMessage(), 'Payment not found') ? 200 : 400;
            Response::json(['error' => $code === 200 ? 'ignored' : 'Invalid webhook'], $code);
        } catch (\Throwable $e) {
            Logger::error('Webhook processing error: ' . $e->getMessage());
            Response::json(['error' => 'Processing error'], 500); // provider will retry
        }
    }
}
