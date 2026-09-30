<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Uuid;

final class Payment
{
    public const STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM payments WHERE id = :id', ['id' => $id]);
    }

    public static function findByUuid(string $uuid): ?array
    {
        return Database::fetch('SELECT * FROM payments WHERE uuid = :u', ['u' => $uuid]);
    }

    public static function findByTransaction(string $provider, string $tx): ?array
    {
        return Database::fetch('SELECT * FROM payments WHERE provider = :p AND transaction_id = :t', ['p' => $provider, 't' => $tx]);
    }

    public static function findByReference(string $provider, string $ref): ?array
    {
        return Database::fetch('SELECT * FROM payments WHERE provider = :p AND provider_reference = :r', ['p' => $provider, 'r' => $ref]);
    }

    public static function createPending(int $userId, array $certification, string $provider): array
    {
        $id = Database::insert(
            'INSERT INTO payments (uuid, user_id, certification_id, amount, currency, provider, status)
             VALUES (:u, :uid, :cid, :amt, :cur, :p, \'pending\')',
            ['u' => Uuid::v4(), 'uid' => $userId, 'cid' => $certification['id'], 'amt' => $certification['price_cents'],
             'cur' => $certification['currency'], 'p' => $provider]
        );
        return self::find($id);
    }

    public static function attachTransaction(int $id, string $transactionId): void
    {
        Database::query('UPDATE payments SET transaction_id = :t WHERE id = :id', ['t' => $transactionId, 'id' => $id]);
    }

    /**
     * A paid payment that has not yet been consumed by an exam attempt.
     * This is the ONLY source of truth for "may this user start this exam".
     */
    public static function availableCredit(int $userId, int $certificationId): ?array
    {
        return Database::fetch(
            "SELECT p.* FROM payments p
             LEFT JOIN exam_attempts a ON a.payment_id = p.id
             WHERE p.user_id = :u AND p.certification_id = :c AND p.status = 'paid' AND a.id IS NULL
             ORDER BY p.paid_at ASC LIMIT 1",
            ['u' => $userId, 'c' => $certificationId]
        );
    }

    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT p.*, c.name AS certification_name FROM payments p JOIN certifications c ON c.id = p.certification_id
             WHERE p.user_id = :u ORDER BY p.created_at DESC LIMIT 50',
            ['u' => $userId]
        );
    }

    public static function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'p.status = :st';
            $params['st'] = $filters['status'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(u.email LIKE :s1 OR p.transaction_id LIKE :s2 OR p.uuid LIKE :s3)';
            $like = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $params += ['s1' => $like, 's2' => $like, 's3' => $like];
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM payments p JOIN users u ON u.id = p.user_id WHERE {$w}", $params);
        $rows = Database::fetchAll(
            "SELECT p.*, u.email, u.first_name, u.last_name, c.name AS certification_name,
                    (SELECT a.uuid FROM exam_attempts a WHERE a.payment_id = p.id) AS attempt_uuid
             FROM payments p JOIN users u ON u.id = p.user_id JOIN certifications c ON c.id = p.certification_id
             WHERE {$w} ORDER BY p.created_at DESC, p.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
