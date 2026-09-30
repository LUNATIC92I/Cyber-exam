<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Audit;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Models\Certification;
use App\Models\Payment;
use App\Models\User;
use App\Services\Mailer;

/**
 * Payment orchestration. The state machine is:
 *   pending → paid | failed,   paid → refunded
 * Transitions are ONLY triggered by authenticated provider webhooks.
 */
final class PaymentService
{
    /** Registry of supported providers. Add new gateways here. */
    public static function gateway(?string $provider = null): PaymentGateway
    {
        $provider ??= (string) Config::get('payments.provider', 'stripe');
        return match ($provider) {
            'stripe'  => new StripeGateway(),
            'sandbox' => new SandboxGateway(),
            // 'paypal'       => new PayPalGateway(),
            // 'mobile_money' => new MobileMoneyGateway(),
            default   => throw new \InvalidArgumentException("Unsupported payment provider: {$provider}"),
        };
    }

    /** Creates a pending payment and returns the provider redirect URL. */
    public static function startCheckout(array $user, array $certification): string
    {
        $gateway = self::gateway();
        $payment = Payment::createPending((int) $user['id'], $certification, $gateway->name());
        try {
            $checkout = $gateway->createCheckout($payment, $user, $certification);
        } catch (\Throwable $e) {
            Database::query("UPDATE payments SET status = 'failed', failure_reason = :r WHERE id = :id",
                ['r' => mb_substr($e->getMessage(), 0, 255), 'id' => $payment['id']]);
            Logger::error('Checkout creation failed: ' . $e->getMessage());
            throw $e;
        }
        Payment::attachTransaction((int) $payment['id'], $checkout['transaction_id']);
        Audit::log('payment.checkout_created', 'payment', $payment['uuid'], ['provider' => $gateway->name(), 'amount' => $payment['amount']]);
        return $checkout['redirect_url'];
    }

    /**
     * Entry point for provider webhooks. Returns a short status string.
     * @throws WebhookException on authentication / payload errors
     */
    public static function handleWebhook(string $provider, string $payload, array $headers): string
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        $event = self::gateway($provider)->parseWebhook($payload, $headers);

        if ($event->action === WebhookEvent::ACTION_IGNORE) {
            return 'ignored';
        }

        $outcome = Database::transaction(function () use ($provider, $event) {
            // Idempotency: each provider event is processed once.
            $already = Database::value('SELECT id FROM webhook_events WHERE provider = :p AND event_id = :e FOR UPDATE',
                ['p' => $provider, 'e' => $event->id]);
            if ($already) {
                return ['status' => 'duplicate'];
            }

            $payment = self::locate($provider, $event);
            if (!$payment) {
                throw new WebhookException('Payment not found for event ' . $event->id);
            }
            $payment = Database::fetch('SELECT * FROM payments WHERE id = :id FOR UPDATE', ['id' => $payment['id']]);

            $status = self::apply($payment, $event);
            Database::query('INSERT INTO webhook_events (provider, event_id, event_type, payment_id) VALUES (:p, :e, :t, :pid)',
                ['p' => $provider, 'e' => $event->id, 't' => $event->type, 'pid' => $payment['id']]);
            return ['status' => $status, 'payment_id' => (int) $payment['id']];
        });

        if ($outcome['status'] === 'paid') {
            self::sendConfirmation($outcome['payment_id']);
        }
        return $outcome['status'];
    }

    private static function locate(string $provider, WebhookEvent $event): ?array
    {
        if ($event->transactionId) {
            $p = Payment::findByTransaction($provider, $event->transactionId);
            if ($p) {
                return $p;
            }
        }
        if ($event->paymentUuid) {
            $p = Payment::findByUuid($event->paymentUuid);
            if ($p && $p['provider'] === $provider) {
                return $p;
            }
        }
        if ($event->providerReference) {
            return Payment::findByReference($provider, $event->providerReference);
        }
        return null;
    }

    private static function apply(array $payment, WebhookEvent $event): string
    {
        $now = Database::now();
        switch ($event->action) {
            case WebhookEvent::ACTION_PAID:
                if ($payment['status'] === 'paid') {
                    return 'already_paid';
                }
                if (!in_array($payment['status'], ['pending', 'failed'], true)) {
                    return 'ignored_state';
                }
                // Never trust the event blindly: amount and currency must match what we charged.
                if ($event->amount !== null && $event->amount !== (int) $payment['amount']) {
                    self::fail($payment, 'Amount mismatch');
                    Audit::log('payment.amount_mismatch', 'payment', $payment['uuid'], ['expected' => $payment['amount'], 'got' => $event->amount], 'system');
                    return 'amount_mismatch';
                }
                if ($event->currency !== null && strtoupper($event->currency) !== strtoupper($payment['currency'])) {
                    self::fail($payment, 'Currency mismatch');
                    return 'currency_mismatch';
                }
                Database::query(
                    "UPDATE payments SET status = 'paid', paid_at = :now, failure_reason = NULL,
                        provider_reference = COALESCE(:ref, provider_reference),
                        transaction_id = COALESCE(transaction_id, :tx) WHERE id = :id",
                    ['now' => $now, 'ref' => $event->providerReference, 'tx' => $event->transactionId, 'id' => $payment['id']]
                );
                Audit::log('payment.paid', 'payment', $payment['uuid'], ['event' => $event->id], 'system');
                return 'paid';

            case WebhookEvent::ACTION_FAILED:
                if ($payment['status'] !== 'pending') {
                    return 'ignored_state';
                }
                self::fail($payment, $event->reason ?? 'Payment failed');
                return 'failed';

            case WebhookEvent::ACTION_REFUNDED:
                if ($payment['status'] !== 'paid') {
                    return 'ignored_state';
                }
                Database::query("UPDATE payments SET status = 'refunded', refunded_at = :now WHERE id = :id",
                    ['now' => $now, 'id' => $payment['id']]);
                Audit::log('payment.refunded', 'payment', $payment['uuid'], ['event' => $event->id], 'system');
                return 'refunded';
        }
        return 'ignored';
    }

    private static function fail(array $payment, string $reason): void
    {
        Database::query("UPDATE payments SET status = 'failed', failure_reason = :r WHERE id = :id",
            ['r' => $reason, 'id' => $payment['id']]);
        Audit::log('payment.failed', 'payment', $payment['uuid'], ['reason' => $reason], 'system');
    }

    private static function sendConfirmation(int $paymentId): void
    {
        try {
            $payment = Payment::find($paymentId);
            $user = User::find((int) $payment['user_id']);
            $cert = Certification::find((int) $payment['certification_id']);
            Mailer::sendTemplate($user['email'], 'Payment Confirmed', 'payment-confirmed',
                ['user' => $user, 'payment' => $payment, 'certification' => $cert]);
        } catch (\Throwable $e) {
            Logger::error('Payment confirmation email failed: ' . $e->getMessage());
        }
    }
}
