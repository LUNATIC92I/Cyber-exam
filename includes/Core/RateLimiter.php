<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window rate limiter backed by MySQL (works on any shared hosting).
 * Keys are hashed so no raw email/IP is stored in the table.
 */
final class RateLimiter
{
    public static function hit(string $bucket, string $identifier, int $maxHits, int $windowSeconds): bool
    {
        $key = hash('sha256', $bucket . '|' . strtolower($identifier));
        $now = Database::now();
        $reset = gmdate('Y-m-d H:i:s', time() + $windowSeconds);

        Database::query(
            'INSERT INTO rate_limits (rl_key, hits, reset_at) VALUES (:k, 1, :reset)
             ON DUPLICATE KEY UPDATE
                hits = IF(reset_at <= :now1, 1, hits + 1),
                reset_at = IF(reset_at <= :now2, :reset2, reset_at)',
            ['k' => $key, 'reset' => $reset, 'now1' => $now, 'now2' => $now, 'reset2' => $reset]
        );
        $hits = (int) Database::value('SELECT hits FROM rate_limits WHERE rl_key = :k', ['k' => $key]);
        return $hits <= $maxHits;
    }

    public static function tooMany(string $bucket, string $identifier, int $maxHits): bool
    {
        $key = hash('sha256', $bucket . '|' . strtolower($identifier));
        $row = Database::fetch('SELECT hits, reset_at FROM rate_limits WHERE rl_key = :k', ['k' => $key]);
        return $row !== null && $row['reset_at'] > Database::now() && (int) $row['hits'] >= $maxHits;
    }

    public static function clear(string $bucket, string $identifier): void
    {
        Database::query('DELETE FROM rate_limits WHERE rl_key = :k',
            ['k' => hash('sha256', $bucket . '|' . strtolower($identifier))]);
    }

    /** Convenience guard: aborts with 429 when the limit is exceeded. */
    public static function enforce(string $bucket, string $identifier, int $maxHits, int $windowSeconds): void
    {
        if (!self::hit($bucket, $identifier, $maxHits, $windowSeconds)) {
            Audit::log('security.rate_limited', null, null, ['bucket' => $bucket]);
            if (Request::wantsJson()) {
                Response::json(['error' => 'Too many requests. Please slow down.'], 429);
            }
            Response::abort(429, 'Too many requests. Please wait a few minutes and try again.');
        }
    }

    public static function purgeExpired(): int
    {
        return Database::query('DELETE FROM rate_limits WHERE reset_at < :now', ['now' => Database::now()])->rowCount();
    }
}
