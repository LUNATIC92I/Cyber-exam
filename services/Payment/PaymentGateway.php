<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Contract every payment provider implements (Stripe today; PayPal,
 * Mobile Money… can be added by implementing this interface and registering
 * the class in PaymentService::gateway()).
 */
interface PaymentGateway
{
    /** Provider identifier stored in payments.provider. */
    public function name(): string;

    /**
     * Creates a hosted checkout for a pending payment.
     * Returns ['transaction_id' => string, 'redirect_url' => string].
     */
    public function createCheckout(array $payment, array $user, array $certification): array;

    /**
     * Authenticates and parses an inbound webhook. MUST verify the provider
     * signature and throw WebhookException when it is invalid.
     */
    public function parseWebhook(string $payload, array $headers): WebhookEvent;
}
