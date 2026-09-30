<?php
declare(strict_types=1);

namespace App\Core;

/** HTTP security headers and transport policy. */
final class Security
{
    public static function sendHeaders(): void
    {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        header_remove('X-Powered-By');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "form-action 'self' https://checkout.stripe.com",
            "upgrade-insecure-requests",
        ]);
        if (!Request::isHttps()) {
            $csp = str_replace('; upgrade-insecure-requests', '', $csp);
        }
        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function enforceHttps(): void
    {
        if (PHP_SAPI === 'cli' || !Config::get('app.force_https') || Request::isHttps()) {
            return;
        }
        $host = parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost';
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }

    /** Pages with personal data must not be cached by shared caches. */
    public static function noStore(): void
    {
        if (!headers_sent() && PHP_SAPI !== 'cli') {
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('Pragma: no-cache');
        }
    }

    /** Keyed hash used for pseudonymising IPs and signing data. */
    public static function hmac(string $data): string
    {
        return hash_hmac('sha256', $data, self::appKey());
    }

    public static function appKey(): string
    {
        $key = (string) Config::get('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }
        if (strlen($key) < 32) {
            throw new \RuntimeException('APP_KEY is missing or too short. Run: php tools/generate-key.php');
        }
        return $key;
    }

    public static function randomToken(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
