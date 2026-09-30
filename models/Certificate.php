<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Certificate
{
    /** Code format: CEX-<CERT CODE>-<YEAR>-<8 chars from an unambiguous alphabet>. */
    public const CODE_PATTERN = '/^CEX-[A-Z0-9]{2,10}-\d{4}-[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/';

    public static function findByCode(string $code): ?array
    {
        return Database::fetch(
            'SELECT cert.*, c.code AS certification_code, c.slug AS certification_slug
             FROM certificates cert JOIN certifications c ON c.id = cert.certification_id
             WHERE cert.certificate_code = :c',
            ['c' => $code]
        );
    }

    public static function findByAttempt(int $attemptId): ?array
    {
        return Database::fetch('SELECT * FROM certificates WHERE attempt_id = :a', ['a' => $attemptId]);
    }

    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT * FROM certificates WHERE user_id = :u ORDER BY issued_at DESC',
            ['u' => $userId]
        );
    }

    public static function normaliseCode(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', trim($input)) ?? '');
    }

    public static function isValidFormat(string $code): bool
    {
        return (bool) preg_match(self::CODE_PATTERN, $code);
    }

    public static function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];
        if (in_array($filters['status'] ?? '', ['valid', 'revoked'], true)) {
            $where[] = 'cert.status = :st';
            $params['st'] = $filters['status'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(cert.certificate_code LIKE :s1 OR cert.candidate_name LIKE :s2 OR u.email LIKE :s3)';
            $like = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $params += ['s1' => $like, 's2' => $like, 's3' => $like];
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM certificates cert JOIN users u ON u.id = cert.user_id WHERE {$w}", $params);
        $rows = Database::fetchAll(
            "SELECT cert.*, u.email,
                (SELECT COUNT(*) FROM certificate_verifications v WHERE v.certificate_id = cert.id) AS verifications
             FROM certificates cert JOIN users u ON u.id = cert.user_id
             WHERE {$w} ORDER BY cert.issued_at DESC, cert.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
