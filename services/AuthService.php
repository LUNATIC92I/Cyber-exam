<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\AdminAuth;
use App\Core\Config;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Security;
use App\Models\Admin;
use App\Models\User;

/**
 * Registration, login (with rate limiting + account lockout), email
 * verification and password reset. Tokens are stored hashed (SHA-256).
 */
final class AuthService
{
    public const VERIFY_TTL = 48 * 3600;
    public const RESET_TTL = 3600;

    /** A valid bcrypt hash of a random string: used to keep timing constant for unknown emails. */
    private const DUMMY_HASH = '$2y$12$5f7/L4f3mpptwY.6cV304udVAWv3O58bSYHs/ugY6Nrmut7.CFDk.';

    public static function register(array $data): array
    {
        $id = User::create($data);
        $user = User::find($id);
        Audit::log('auth.registered', 'user', $id, [], 'user', $id);
        self::sendVerification($user);
        return $user;
    }

    /**
     * @return array{ok:bool, error?:string, user?:array}
     */
    public static function attemptLogin(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $ip = Request::ip();
        $sec = Config::get('security');
        $generic = 'Invalid email or password.';

        // Throttle per (email, ip) and per ip.
        if (!RateLimiter::hit('login:ip', $ip, 30, (int) $sec['login_window_seconds'])
            || !RateLimiter::hit('login:email-ip', $email . '|' . $ip, (int) $sec['login_max_attempts'], (int) $sec['login_window_seconds'])) {
            Audit::log('auth.login_throttled', 'user', null, ['email' => $email], 'guest');
            return ['ok' => false, 'error' => 'Too many sign-in attempts. Please wait 15 minutes and try again.'];
        }

        $user = User::findByEmail($email);
        if (!$user) {
            password_verify($password, self::DUMMY_HASH);
            return ['ok' => false, 'error' => $generic];
        }
        if ($user['locked_until'] && $user['locked_until'] > Database::now()) {
            Audit::log('auth.login_locked', 'user', $user['id'], [], 'guest');
            return ['ok' => false, 'error' => 'This account is temporarily locked after repeated failed attempts. Try again later or reset your password.'];
        }
        if (!password_verify($password, $user['password_hash'])) {
            User::recordFailedLogin((int) $user['id'], (int) $sec['account_lock_threshold'], (int) $sec['account_lock_minutes']);
            Audit::log('auth.login_failed', 'user', $user['id'], [], 'guest');
            return ['ok' => false, 'error' => $generic];
        }
        if ($user['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account has been suspended. Please contact support.'];
        }

        User::rehashIfNeeded($user, $password);
        User::recordSuccessfulLogin((int) $user['id'], $ip);
        RateLimiter::clear('login:email-ip', $email . '|' . $ip);
        Auth::login($user);
        Audit::log('auth.login', 'user', $user['id'], [], 'user', (int) $user['id']);
        return ['ok' => true, 'user' => $user];
    }

    public static function attemptAdminLogin(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $ip = Request::ip();
        $generic = 'Invalid credentials.';
        if (!RateLimiter::hit('admin-login:ip', $ip, 10, 900)
            || !RateLimiter::hit('admin-login:email', $email, 5, 900)) {
            Audit::log('admin.login_throttled', 'admin', null, ['email' => $email], 'guest');
            return ['ok' => false, 'error' => 'Too many attempts. Please wait 15 minutes.'];
        }
        $admin = Admin::findByEmail($email);
        if (!$admin) {
            password_verify($password, self::DUMMY_HASH);
            return ['ok' => false, 'error' => $generic];
        }
        if (($admin['locked_until'] && $admin['locked_until'] > Database::now()) || $admin['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account is locked or disabled.'];
        }
        if (!password_verify($password, $admin['password_hash'])) {
            Admin::recordFailedLogin((int) $admin['id'], 5, 30);
            Audit::log('admin.login_failed', 'admin', $admin['id'], [], 'guest');
            return ['ok' => false, 'error' => $generic];
        }
        Admin::recordSuccessfulLogin((int) $admin['id'], $ip);
        RateLimiter::clear('admin-login:email', $email);
        AdminAuth::login($admin);
        Audit::log('admin.login', 'admin', $admin['id'], [], 'admin', (int) $admin['id']);
        return ['ok' => true, 'admin' => $admin];
    }

    public static function sendVerification(array $user): void
    {
        $token = Security::randomToken(32);
        Database::query('DELETE FROM email_verifications WHERE user_id = :u AND used_at IS NULL', ['u' => $user['id']]);
        Database::query('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (:u, :h, :e)',
            ['u' => $user['id'], 'h' => hash('sha256', $token), 'e' => gmdate('Y-m-d H:i:s', time() + self::VERIFY_TTL)]);
        Mailer::sendTemplate($user['email'], 'Confirm your email address', 'verify-email',
            ['user' => $user, 'link' => url('verify-email.php', ['token' => $token])]);
    }

    public static function verifyEmail(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,100}$/', $token)) {
            return null;
        }
        $row = Database::fetch('SELECT * FROM email_verifications WHERE token_hash = :h', ['h' => hash('sha256', $token)]);
        if (!$row || $row['used_at'] !== null || $row['expires_at'] < Database::now()) {
            return null;
        }
        Database::query('UPDATE email_verifications SET used_at = :n WHERE id = :id', ['n' => Database::now(), 'id' => $row['id']]);
        User::markEmailVerified((int) $row['user_id']);
        Audit::log('auth.email_verified', 'user', $row['user_id'], [], 'user', (int) $row['user_id']);
        return User::find((int) $row['user_id']);
    }

    /** Always behaves the same whether the account exists or not (no user enumeration). */
    public static function requestPasswordReset(string $email): void
    {
        $user = User::findByEmail($email);
        if (!$user || $user['status'] !== 'active') {
            return;
        }
        $token = Security::randomToken(32);
        Database::query('UPDATE password_resets SET used_at = :n WHERE user_id = :u AND used_at IS NULL', ['n' => Database::now(), 'u' => $user['id']]);
        Database::query('INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address) VALUES (:u, :h, :e, :ip)',
            ['u' => $user['id'], 'h' => hash('sha256', $token), 'e' => gmdate('Y-m-d H:i:s', time() + self::RESET_TTL),
             'ip' => PHP_SAPI === 'cli' ? null : Request::ip()]);
        Audit::log('auth.password_reset_requested', 'user', $user['id'], [], 'guest');
        Mailer::sendTemplate($user['email'], 'Reset your password', 'password-reset',
            ['user' => $user, 'link' => url('reset-password.php', ['token' => $token])]);
    }

    public static function findResetToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,100}$/', $token)) {
            return null;
        }
        $row = Database::fetch('SELECT * FROM password_resets WHERE token_hash = :h', ['h' => hash('sha256', $token)]);
        return ($row && $row['used_at'] === null && $row['expires_at'] >= Database::now()) ? $row : null;
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $row = self::findResetToken($token);
        if (!$row) {
            return false;
        }
        Database::transaction(function () use ($row, $password) {
            Database::query('UPDATE password_resets SET used_at = :n WHERE id = :id', ['n' => Database::now(), 'id' => $row['id']]);
            User::updatePassword((int) $row['user_id'], $password);
            // Receiving the reset link proves control of the mailbox.
            User::markEmailVerified((int) $row['user_id']);
        });
        Audit::log('auth.password_reset', 'user', $row['user_id'], [], 'user', (int) $row['user_id']);
        return true;
    }
}
