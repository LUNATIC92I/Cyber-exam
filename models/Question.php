<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Question
{
    public const DIFFICULTIES = ['beginner', 'intermediate', 'advanced'];
    public const OPTIONS = ['A', 'B', 'C', 'D'];

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM questions WHERE id = :id', ['id' => $id]);
    }

    public static function hash(string $question): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/', ' ', trim($question))));
    }

    public static function save(array $d, ?int $id = null): int
    {
        $params = [
            'cid' => (int) $d['certification_id'], 'cat' => $d['category'], 'dif' => $d['difficulty'],
            'q' => $d['question'], 'a' => $d['option_a'], 'b' => $d['option_b'], 'c' => $d['option_c'], 'd' => $d['option_d'],
            'co' => $d['correct_option'], 'ex' => $d['explanation'], 'act' => (int) ($d['is_active'] ?? 1),
            'h' => self::hash($d['question']),
        ];
        if ($id === null) {
            return Database::insert(
                'INSERT INTO questions (certification_id, category, difficulty, question, option_a, option_b, option_c, option_d,
                    correct_option, explanation, is_active, content_hash)
                 VALUES (:cid, :cat, :dif, :q, :a, :b, :c, :d, :co, :ex, :act, :h)',
                $params
            );
        }
        Database::query(
            'UPDATE questions SET certification_id = :cid, category = :cat, difficulty = :dif, question = :q, option_a = :a,
                option_b = :b, option_c = :c, option_d = :d, correct_option = :co, explanation = :ex, is_active = :act,
                content_hash = :h WHERE id = :id',
            $params + ['id' => $id]
        );
        return $id;
    }

    /**
     * Deletes a question. Questions already used in an exam are archived (deactivated)
     * instead, so historical results stay intact.
     */
    public static function delete(int $id): string
    {
        $used = (int) Database::value('SELECT COUNT(*) FROM exam_questions WHERE question_id = :id', ['id' => $id]);
        if ($used > 0) {
            Database::query('UPDATE questions SET is_active = 0 WHERE id = :id', ['id' => $id]);
            return 'archived';
        }
        Database::query('DELETE FROM questions WHERE id = :id', ['id' => $id]);
        return 'deleted';
    }

    public static function paginate(array $filters, int $page, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['certification_id'])) {
            $where[] = 'q.certification_id = :cid';
            $params['cid'] = (int) $filters['certification_id'];
        }
        if (!empty($filters['difficulty']) && in_array($filters['difficulty'], self::DIFFICULTIES, true)) {
            $where[] = 'q.difficulty = :dif';
            $params['dif'] = $filters['difficulty'];
        }
        if (($filters['search'] ?? '') !== '') {
            $where[] = '(q.question LIKE :s1 OR q.category LIKE :s2)';
            $like = '%' . addcslashes($filters['search'], '%_\\') . '%';
            $params += ['s1' => $like, 's2' => $like];
        }
        if (($filters['status'] ?? '') === 'archived') {
            $where[] = 'q.is_active = 0';
        } elseif (($filters['status'] ?? '') === 'active') {
            $where[] = 'q.is_active = 1';
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM questions q WHERE {$w}", $params);
        $rows = Database::fetchAll(
            "SELECT q.*, c.name AS certification_name, c.code AS certification_code
             FROM questions q JOIN certifications c ON c.id = q.certification_id
             WHERE {$w} ORDER BY q.certification_id, q.id LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public static function categories(int $certificationId): array
    {
        return Database::fetchAll(
            'SELECT category, COUNT(*) AS n FROM questions WHERE certification_id = :c AND is_active = 1 GROUP BY category ORDER BY category',
            ['c' => $certificationId]
        );
    }
}
