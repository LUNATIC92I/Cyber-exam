<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Config;
use App\Core\Security;

/**
 * Local payment simulator for development and automated tests.
 * It reproduces the Stripe flow end-to-end: a hosted "checkout" page and a
 * SIGNED webhook that goes through exactly the same verification and state
 * machine as real payments. It is refused when APP_ENV=production.
 */
final class SandboxGateway implements PaymentGateway
{
    public function __construct()
    {
        if (self::disabled()) {
            throw new \RuntimeException('The sandbox payment provider is disabled in production.');
        }
    }

    public static function disabled(): bool
    {
        return Config::get('app.env') === 'production';
    }

    public function name(): string
    {
        return 'sandbox';
    }

    public static function secret(): string
    {
        $s = (string) Config::get('payments.sandbox.secret');
        return $s !== '' ? $s : 'sbx_' . Security::hmac('sandbox-webhook');
    }

    public function createCheckout(array $payment, array $user, array $certification): array
    {
        $tx = 'sbx_cs_' . bin2hex(random_bytes(12));
        return ['transaction_id' => $tx, 'redirect_url' => url('checkout.php', ['sandbox' => $payment['uuid']])];
    }

    public function parseWebhook(string $payload, array $headers): WebhookEvent
    {
        StripeSignature::verify($payload, (string) ($headers['stripe-signature'] ?? ''), self::secret());
        return StripeGateway::mapEvent($payload);
    }

    /** Builds a signed Stripe-shaped event for the given payment (used by the simulator page and tests). */
    public static function buildEvent(array $payment, string $type, string $paymentStatus = 'paid'): array
    {
        $payload = json_encode([
            'id' => 'evt_sbx_' . bin2hex(random_bytes(10)),
            'type' => $type,
            'data' => ['object' => [
                'id' => $payment['transaction_id'],
                'client_reference_id' => $payment['uuid'],
                'payment_status' => $paymentStatus,
                'payment_intent' => 'pi_sbx_' . substr(hash('sha256', $payment['uuid']), 0, 20),
                'amount_total' => (int) $payment['amount'],
                'currency' => strtolower($payment['currency']),
                'refunded' => $type === 'charge.refunded',
                'metadata' => ['payment_uuid' => $payment['uuid']],
            ]],
        ], JSON_UNESCAPED_SLASHES);
        return ['payload' => $payload, 'headers' => ['stripe-signature' => StripeSignature::header($payload, self::secret())]];
    }
}
