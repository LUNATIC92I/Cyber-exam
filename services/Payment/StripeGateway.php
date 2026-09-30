<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Config;

/**
 * Stripe Checkout integration using the REST API directly (no SDK needed).
 * Payment confirmation happens ONLY through the signed webhook.
 */
final class StripeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(array $payment, array $user, array $certification): array
    {
        $secret = (string) Config::get('payments.stripe.secret_key');
        if ($secret === '') {
            throw new \RuntimeException('Stripe is not configured (STRIPE_SECRET_KEY missing).');
        }
        $params = [
            'mode' => 'payment',
            'client_reference_id' => $payment['uuid'],
            'customer_email' => $user['email'],
            'success_url' => url('checkout.php', ['payment' => $payment['uuid'], 'status' => 'success']),
            'cancel_url' => url('checkout.php', ['payment' => $payment['uuid'], 'status' => 'cancelled']),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment['currency']),
                    'unit_amount' => (int) $payment['amount'],
                    'product_data' => [
                        'name' => $certification['name'] . ' — Certification Exam',
                        'description' => '100-question professional assessment · ' . $certification['duration_minutes'] . ' minutes',
                    ],
                ],
            ]],
            'metadata' => ['payment_uuid' => $payment['uuid'], 'certification' => $certification['code']],
            'payment_intent_data' => ['metadata' => ['payment_uuid' => $payment['uuid']]],
        ];
        $session = $this->request('POST', '/checkout/sessions', $params, $secret, 'cex-' . $payment['uuid']);
        if (empty($session['id']) || empty($session['url'])) {
            throw new \RuntimeException('Unexpected response from Stripe.');
        }
        return ['transaction_id' => $session['id'], 'redirect_url' => $session['url']];
    }

    public function parseWebhook(string $payload, array $headers): WebhookEvent
    {
        StripeSignature::verify($payload, (string) ($headers['stripe-signature'] ?? ''),
            (string) Config::get('payments.stripe.webhook_secret'));
        return self::mapEvent($payload);
    }

    /** Maps a (verified) Stripe event JSON to a provider-agnostic event. */
    public static function mapEvent(string $payload): WebhookEvent
    {
        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
            throw new WebhookException('Invalid event payload.');
        }
        $obj = $event['data']['object'] ?? [];
        $type = (string) $event['type'];

        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $paid = ($obj['payment_status'] ?? '') === 'paid';
                return new WebhookEvent($event['id'], $type, $paid ? WebhookEvent::ACTION_PAID : WebhookEvent::ACTION_IGNORE,
                    $obj['client_reference_id'] ?? ($obj['metadata']['payment_uuid'] ?? null), $obj['id'] ?? null,
                    is_string($obj['payment_intent'] ?? null) ? $obj['payment_intent'] : null,
                    isset($obj['amount_total']) ? (int) $obj['amount_total'] : null,
                    isset($obj['currency']) ? strtoupper((string) $obj['currency']) : null);
            case 'checkout.session.async_payment_failed':
            case 'checkout.session.expired':
                return new WebhookEvent($event['id'], $type, WebhookEvent::ACTION_FAILED,
                    $obj['client_reference_id'] ?? ($obj['metadata']['payment_uuid'] ?? null), $obj['id'] ?? null,
                    null, null, null, $type === 'checkout.session.expired' ? 'Checkout session expired' : 'Payment failed');
            case 'charge.refunded':
                $refundedFully = ($obj['refunded'] ?? false) === true;
                return new WebhookEvent($event['id'], $type, $refundedFully ? WebhookEvent::ACTION_REFUNDED : WebhookEvent::ACTION_IGNORE,
                    $obj['metadata']['payment_uuid'] ?? null, null,
                    is_string($obj['payment_intent'] ?? null) ? $obj['payment_intent'] : null);
            default:
                return new WebhookEvent($event['id'], $type, WebhookEvent::ACTION_IGNORE);
        }
    }

    private function request(string $method, string $path, array $params, string $secret, ?string $idempotencyKey = null): array
    {
        $ch = curl_init((string) Config::get('payments.stripe.api_base') . $path);
        $headers = ['Authorization: Bearer ' . $secret, 'Stripe-Version: 2024-06-20'];
        if ($idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException('Stripe request failed: ' . $err);
        }
        $data = json_decode((string) $body, true) ?: [];
        if ($status >= 400) {
            throw new \RuntimeException('Stripe error: ' . ($data['error']['message'] ?? ('HTTP ' . $status)));
        }
        return $data;
    }
}
