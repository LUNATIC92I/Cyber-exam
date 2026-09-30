<?php
declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Stripe-compatible webhook signature scheme:
 *   header  Stripe-Signature: t=<unix>,v1=<hex hmac>
 *   signed  HMAC-SHA256(secret, "<t>.<raw body>")
 */
final class StripeSignature
{
    public const TOLERANCE = 300;

    public static function verify(string $payload, string $header, string $secret, int $tolerance = self::TOLERANCE, ?int $now = null): void
    {
        if ($secret === '') {
            throw new WebhookException('Webhook secret not configured.');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't' && ctype_digit($v)) {
                $timestamp = (int) $v;
            } elseif ($k === 'v1' && $v !== '') {
                $signatures[] = $v;
            }
        }
        if ($timestamp === null || !$signatures) {
            throw new WebhookException('Malformed signature header.');
        }
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new WebhookException('Signature timestamp outside tolerance.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return;
            }
        }
        throw new WebhookException('Signature mismatch.');
    }

    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, $secret);
    }
}
