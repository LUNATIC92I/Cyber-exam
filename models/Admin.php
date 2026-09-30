<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

final class Admin
{
    public const ROLES = ['super_admin' => 'Super administrator', 'admin' => 'Administrator', 'support' => 'Support (read-only)'];

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM admins WHERE email = :e', ['e' => mb_strtolower(trim($email))]);
    }

    public static function create(string $name, string $email, string $password, string $role = 'admin'): int
    {
        if (!isset(self::ROLES[$role])) {
            throw new \InvalidArgumentException('Invalid role');
        }
        return Database::insert(
            'INSERT INTO admins (uuid, display_name, email, password_hash, role) VALUES (:u, :n, :e, :h, :r)',
            ['u' => Uuid::v4(), 'n' => $name, 'e' => mb_strtolower(trim($email)),
             'h' => password_hash($password, PASSWORD_DEFAULT), 'r' => $role]
        );
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM admins');
    }

    public static function all(): array
    {
        return Database::fetchAll('SELECT id, display_name, email, role, status, last_login_at, created_at FROM admins ORDER BY id');
    }

    public static function recordFailedLogin(int $id, int $threshold, int $lockMinutes): void
    {
        Database::query(
            'UPDATE admins SET failed_logins = failed_logins + 1,
                locked_until = IF(failed_logins + 1 >= :t, :until, locked_until) WHERE id = :id',
            ['t' => $threshold, 'until' => gmdate('Y-m-d H:i:s', time() + $lockMinutes * 60), 'id' => $id]
        );
    }

    public static function recordSuccessfulLogin(int $id, string $ip): void
    {
        Database::query('UPDATE admins SET failed_logins = 0, locked_until = NULL, last_login_at = :now, last_login_ip = :ip WHERE id = :id',
            ['now' => Database::now(), 'ip' => $ip, 'id' => $id]);
    }
}
