<?php
declare(strict_types=1);

namespace App\Services\Payment;

/** Provider-agnostic, already-authenticated webhook event. */
final class WebhookEvent
{
    public const ACTION_PAID = 'paid';
    public const ACTION_FAILED = 'failed';
    public const ACTION_REFUNDED = 'refunded';
    public const ACTION_IGNORE = 'ignore';

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $action,
        public readonly ?string $paymentUuid = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $providerReference = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $reason = null,
    ) {
    }
}
