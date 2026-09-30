<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Synchroniser-token CSRF protection. One random token per session,
 * accepted from the `_csrf` form field or the `X-CSRF-Token` header.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    /** Aborts the request with HTTP 419 when the token is missing or wrong. */
    public static function verifyRequest(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::validate(is_string($token) ? $token : null)) {
            Audit::log('security.csrf_rejected', null, null, ['path' => $_SERVER['REQUEST_URI'] ?? '']);
            if (Request::wantsJson()) {
                Response::json(['error' => 'Invalid or expired security token. Please reload the page.'], 419);
            }
            Response::abort(419, 'Your security token has expired. Please go back, reload the page and try again.');
        }
    }
}
