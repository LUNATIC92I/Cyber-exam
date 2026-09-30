<?php
declare(strict_types=1);

namespace App\Core;

/** Candidate authentication state (session based). */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('user_login_at', time());
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
        self::$user = null;
        self::$loaded = true;
    }

    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = self::id();
            if ($id) {
                $user = Database::fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
                if (!$user || $user['status'] !== 'active') {
                    Session::forget('user_id');
                    $user = null;
                }
                self::$user = $user;
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Gate for candidate pages; redirects guests to the login page. */
    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            if (Request::wantsJson()) {
                Response::json(['error' => 'Authentication required.'], 401);
            }
            Session::set('intended', $_SERVER['REQUEST_URI'] ?? null);
            Session::flash('info', 'Please sign in to continue.');
            Response::redirect('/login.php');
        }
        return $user;
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$user = null;
    }
}
