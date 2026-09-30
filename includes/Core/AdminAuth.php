<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Back-office authentication with role-based access control.
 * Admin sessions are independent from candidate sessions.
 */
final class AdminAuth
{
    /** Permission matrix. */
    private const ROLE_PERMISSIONS = [
        'support' => [
            'dashboard.view', 'users.view', 'exams.view', 'payments.view', 'certificates.view',
            'questions.view', 'certifications.view',
        ],
        'admin' => [
            'dashboard.view', 'users.view', 'users.manage', 'exams.view', 'payments.view',
            'certificates.view', 'certificates.manage', 'questions.view', 'questions.manage',
            'certifications.view', 'certifications.manage',
        ],
        'super_admin' => ['*'],
    ];

    private static ?array $admin = null;
    private static bool $loaded = false;

    public static function login(array $admin): void
    {
        Session::regenerate();
        Session::set('admin_id', (int) $admin['id']);
        self::$admin = $admin;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::forget('admin_id');
        self::$admin = null;
        self::$loaded = true;
    }

    public static function id(): ?int
    {
        $id = $_SESSION['admin_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function admin(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = self::id();
            if ($id) {
                $a = Database::fetch('SELECT * FROM admins WHERE id = :id', ['id' => $id]);
                if (!$a || $a['status'] !== 'active') {
                    Session::forget('admin_id');
                    $a = null;
                }
                self::$admin = $a;
            }
        }
        return self::$admin;
    }

    public static function can(string $permission, ?array $admin = null): bool
    {
        $admin ??= self::admin();
        if (!$admin) {
            return false;
        }
        $perms = self::ROLE_PERMISSIONS[$admin['role']] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    /** Gate for back-office pages. */
    public static function require(string $permission = 'dashboard.view'): array
    {
        $admin = self::admin();
        if (!$admin) {
            if (Request::wantsJson()) {
                Response::json(['error' => 'Authentication required.'], 401);
            }
            Response::redirect('/admin/login.php');
        }
        if (!self::can($permission, $admin)) {
            Audit::log('security.admin_forbidden', null, null, ['permission' => $permission]);
            Response::abort(403, 'Your role does not allow this action.');
        }
        return $admin;
    }

    public static function refresh(): void
    {
        self::$loaded = false;
        self::$admin = null;
    }
}
