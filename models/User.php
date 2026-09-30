<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

final class User
{
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE email = :e', ['e' => mb_strtolower(trim($email))]);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO users (uuid, first_name, last_name, email, country, password_hash)
             VALUES (:uuid, :fn, :ln, :email, :country, :hash)',
            [
                'uuid' => Uuid::v4(),
                'fn' => $data['first_name'],
                'ln' => $data['last_name'],
                'email' => mb_strtolower(trim($data['email'])),
                'country' => $data['country'],
                'hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            ]
        );
    }

    public static function fullName(array $u): string
    {
        return trim($u['first_name'] . ' ' . $u['last_name']);
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::query('UPDATE users SET password_hash = :h, failed_logins = 0, locked_until = NULL WHERE id = :id',
            ['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function rehashIfNeeded(array $user, string $password): void
    {
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password_hash = :h WHERE id = :id',
                ['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
        }
    }

    public static function markEmailVerified(int $id): void
    {
        Database::query('UPDATE users SET email_verified_at = COALESCE(email_verified_at, :now) WHERE id = :id',
            ['now' => Database::now(), 'id' => $id]);
    }

    public static function recordFailedLogin(int $id, int $threshold, int $lockMinutes): void
    {
        Database::query(
            'UPDATE users SET failed_logins = failed_logins + 1,
                locked_until = IF(failed_logins + 1 >= :t, :until, locked_until)
             WHERE id = :id',
            ['t' => $threshold, 'until' => gmdate('Y-m-d H:i:s', time() + $lockMinutes * 60), 'id' => $id]
        );
    }

    public static function recordSuccessfulLogin(int $id, string $ip): void
    {
        Database::query('UPDATE users SET failed_logins = 0, locked_until = NULL, last_login_at = :now, last_login_ip = :ip WHERE id = :id',
            ['now' => Database::now(), 'ip' => $ip, 'id' => $id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::query('UPDATE users SET status = :s WHERE id = :id', ['s' => $status, 'id' => $id]);
    }

    /** Candidate statistics for the dashboard. */
    public static function stats(int $userId): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS taken,
                    COALESCE(SUM(passed = 1), 0) AS passed,
                    COALESCE(SUM(passed = 0), 0) AS failed,
                    AVG(score_percent) AS avg_score
             FROM exam_attempts WHERE user_id = :u AND status IN ('submitted','expired')",
            ['u' => $userId]
        );
        $certs = (int) Database::value("SELECT COUNT(*) FROM certificates WHERE user_id = :u AND status = 'valid'", ['u' => $userId]);
        return [
            'taken' => (int) $row['taken'],
            'passed' => (int) $row['passed'],
            'failed' => (int) $row['failed'],
            'certificates' => $certs,
            'average' => $row['avg_score'] === null ? 0.0 : round((float) $row['avg_score'], 1),
        ];
    }

    public static function paginate(string $search, int $page, int $perPage = 20): array
    {
        $where = '1=1';
        $params = [];
        if ($search !== '') {
            $where = '(email LIKE :s1 OR first_name LIKE :s2 OR last_name LIKE :s3 OR CONCAT(first_name," ",last_name) LIKE :s4)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params = ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like];
        }
        $total = (int) Database::value("SELECT COUNT(*) FROM users WHERE {$where}", $params);
        $rows = Database::fetchAll(
            "SELECT u.*,
                (SELECT COUNT(*) FROM exam_attempts a WHERE a.user_id = u.id AND a.status <> 'in_progress') AS exams,
                (SELECT COUNT(*) FROM certificates c WHERE c.user_id = u.id AND c.status = 'valid') AS certs
             FROM users u WHERE {$where} ORDER BY u.created_at DESC, u.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
