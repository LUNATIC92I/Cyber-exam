<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session: strict mode, HttpOnly + SameSite cookies, Secure on HTTPS,
 * ID regeneration on privilege change, idle and absolute timeouts, flash messages.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' && !defined('CEX_TESTING_SESSION')) {
            self::$started = true;
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        if (PHP_VERSION_ID < 80400) { // deprecated (and already strong by default) from PHP 8.4
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');
        }

        $secure = Request::isHttps();
        session_name(Config::get('session.name', 'cex_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::cookiePath(),
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        $now = time();
        $idle = (int) Config::get('session.idle_timeout', 10800);
        $absolute = (int) Config::get('session.absolute_timeout', 43200);
        $created = $_SESSION['_created'] ?? $now;
        $last = $_SESSION['_last'] ?? $now;

        if (($now - $last) > $idle || ($now - $created) > $absolute) {
            self::destroy();
            session_start();
            $_SESSION['_flash']['info'] = 'Your session expired. Please sign in again.';
            $created = $now;
        }
        $_SESSION['_created'] = $created;
        $_SESSION['_last'] = $now;
    }

    private static function cookiePath(): string
    {
        $path = parse_url((string) Config::get('app.url'), PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') . '/';
    }

    /** Must be called on every authentication state change (session fixation defence). */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_created'] = time();
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
                'httponly' => true, 'samesite' => 'Lax',
            ]);
            session_destroy();
            self::$started = false;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /** @return array<string,string> */
    public static function pullFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_csrf'], $input['current_password']);
        $_SESSION['_old'] = $input;
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    public static function pullOld(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return $old;
    }

    public static function pullErrors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $errors;
    }
}
