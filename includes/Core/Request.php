<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?array $jsonBody = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Trimmed string input from POST (then GET). Arrays are rejected. */
    public static function input(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function query(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::input($key);
        return ctype_digit($v) ? (int) $v : $default;
    }

    /** Raw string from POST only (e.g. passwords – never trimmed). */
    public static function raw(string $key): string
    {
        $v = $_POST[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    public static function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = self::input($k);
        }
        return $out;
    }

    public static function json(): array
    {
        if (self::$jsonBody === null) {
            $raw = file_get_contents('php://input') ?: '';
            $data = json_decode($raw, true);
            self::$jsonBody = is_array($data) ? $data : [];
        }
        return self::$jsonBody;
    }

    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json') || str_contains($ct, 'application/json')
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (self::fromTrustedProxy() && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    private static function fromTrustedProxy(): bool
    {
        $proxies = Config::get('app.trusted_proxies', []);
        return $proxies && in_array($_SERVER['REMOTE_ADDR'] ?? '', $proxies, true);
    }

    /** Client IP. X-Forwarded-For is only honoured from configured trusted proxies. */
    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (self::fromTrustedProxy() && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                $ip = $first;
            }
        }
        return $ip;
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
