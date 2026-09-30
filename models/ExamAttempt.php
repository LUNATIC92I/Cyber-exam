<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ExamAttempt
{
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM exam_attempts WHERE id = :id', ['id' => $id]);
    }

    public static function findByUuid(string $uuid): ?array
    {
        return Database::fetch('SELECT * FROM exam_attempts WHERE uuid = :u', ['u' => $uuid]);
    }

    public static function findForUpdate(int $id): ?array
    {
        return Database::fetch('SELECT * FROM exam_attempts WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    public static function activeForUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT a.*, c.name AS certification_name, c.slug FROM exam_attempts a
             JOIN certifications c ON c.id = a.certification_id
             WHERE a.user_id = :u AND a.status = 'in_progress' ORDER BY a.started_at DESC",
            ['u' => $userId]
        );
    }

    public static function activeFor(int $userId, int $certificationId): ?array
    {
        return Database::fetch(
            "SELECT * FROM exam_attempts WHERE user_id = :u AND certification_id = :c AND status = 'in_progress' LIMIT 1",
            ['u' => $userId, 'c' => $certificationId]
        );
    }

    public static function historyForUser(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT a.*, c.name AS certification_name, c.code AS certification_code, cert.certificate_code, cert.status AS certificate_status
             FROM exam_attempts a
             JOIN certifications c ON c.id = a.certification_id
             LEFT JOIN certificates cert ON cert.attempt_id = a.id
             WHERE a.user_id = :u AND a.status <> 'in_progress'
             ORDER BY a.submitted_at DESC LIMIT :lim",
            ['u' => $userId, 'lim' => $limit]
        );
    }

    /** Questions of an attempt in display order (without correct answers). */
    public static function questions(int $attemptId): array
    {
        return Database::fetchAll(
            'SELECT eq.id AS eq_id, eq.position, eq.option_order, q.id AS question_id, q.question, q.category, q.difficulty,
                    q.option_a, q.option_b, q.option_c, q.option_d, ans.selected_option
             FROM exam_questions eq
             JOIN questions q ON q.id = eq.question_id
             LEFT JOIN answers ans ON ans.exam_question_id = eq.id
             WHERE eq.attempt_id = :a ORDER BY eq.position',
            ['a' => $attemptId]
        );
    }

    public static function answeredCount(int $attemptId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM answers WHERE attempt_id = :a', ['a' => $attemptId]);
    }

    /** Expired attempts still marked in progress (for the finaliser / cron). */
    public static function overdue(int $limit = 100): array
    {
        return Database::fetchAll(
            "SELECT id FROM exam_attempts WHERE status = 'in_progress' AND expires_at <= :now ORDER BY expires_at LIMIT :lim",
            ['now' => Database::now(), 'lim' => $limit]
        );
    }

    public static function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['certification_id'])) {
            $where[] = 'a.certification_id = :cid';
            $params['cid'] = (int) $filters['certification_id'];
        }
        if (($filters['result'] ?? '') === 'passed') {
            $where[] = 'a.passed = 1';
        } elseif (($filters['result'] ?? '') === 'failed') {
            $where[] = 'a.passed = 0';
        } elseif (($filters['result'] ?? '') === 'in_progress') {
            $where[] = "a.status = 'in_progress'";
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(u.email LIKE :s1 OR CONCAT(u.first_name," ",u.last_name) LIKE :s2)';
            $like = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $params += ['s1' => $like, 's2' => $like];
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM exam_attempts a JOIN users u ON u.id = a.user_id WHERE {$w}", $params);
        $rows = Database::fetchAll(
            "SELECT a.*, u.email, u.first_name, u.last_name, c.name AS certification_name, cert.certificate_code
             FROM exam_attempts a JOIN users u ON u.id = a.user_id JOIN certifications c ON c.id = a.certification_id
             LEFT JOIN certificates cert ON cert.attempt_id = a.id
             WHERE {$w} ORDER BY a.started_at DESC, a.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}
