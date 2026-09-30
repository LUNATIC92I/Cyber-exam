<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Certification
{
    public const ACCENTS = ['blue', 'cyan', 'violet', 'teal', 'indigo', 'sky', 'emerald'];

    public static function active(): array
    {
        return array_map([self::class, 'hydrate'], Database::fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM questions q WHERE q.certification_id = c.id AND q.is_active = 1) AS bank_size
             FROM certifications c WHERE c.is_active = 1 ORDER BY c.sort_order, c.id'
        ));
    }

    public static function all(): array
    {
        return array_map([self::class, 'hydrate'], Database::fetchAll(
            'SELECT c.*,
                (SELECT COUNT(*) FROM questions q WHERE q.certification_id = c.id AND q.is_active = 1) AS bank_size,
                (SELECT COUNT(*) FROM exam_attempts a WHERE a.certification_id = c.id AND a.status <> \'in_progress\') AS attempts
             FROM certifications c ORDER BY c.sort_order, c.id'
        ));
    }

    public static function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM certifications WHERE id = :id', ['id' => $id]);
        return $row ? self::hydrate($row) : null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = Database::fetch('SELECT * FROM certifications WHERE slug = :s', ['s' => $slug]);
        return $row ? self::hydrate($row) : null;
    }

    public static function findByCode(string $code): ?array
    {
        $row = Database::fetch('SELECT * FROM certifications WHERE code = :c', ['c' => strtoupper($code)]);
        return $row ? self::hydrate($row) : null;
    }

    public static function hydrate(array $row): array
    {
        $row['topics'] = is_string($row['topics'] ?? null) ? (json_decode($row['topics'], true) ?: []) : ($row['topics'] ?? []);
        foreach (['id', 'question_count', 'duration_minutes', 'passing_score', 'price_cents', 'is_active', 'sort_order'] as $k) {
            if (isset($row[$k])) {
                $row[$k] = (int) $row[$k];
            }
        }
        return $row;
    }

    public static function save(array $d, ?int $id = null): int
    {
        $params = [
            'code' => strtoupper($d['code']), 'slug' => $d['slug'], 'name' => $d['name'], 'tagline' => $d['tagline'],
            'description' => $d['description'], 'topics' => json_encode(array_values($d['topics']), JSON_UNESCAPED_UNICODE),
            'accent' => $d['accent'], 'qc' => (int) $d['question_count'], 'dur' => (int) $d['duration_minutes'],
            'pass' => (int) $d['passing_score'], 'price' => (int) $d['price_cents'], 'cur' => $d['currency'],
            'active' => (int) $d['is_active'], 'sort' => (int) $d['sort_order'],
        ];
        if ($id === null) {
            return Database::insert(
                'INSERT INTO certifications (code, slug, name, tagline, description, topics, accent, question_count,
                    duration_minutes, passing_score, price_cents, currency, is_active, sort_order)
                 VALUES (:code, :slug, :name, :tagline, :description, :topics, :accent, :qc, :dur, :pass, :price, :cur, :active, :sort)',
                $params
            );
        }
        Database::query(
            'UPDATE certifications SET code = :code, slug = :slug, name = :name, tagline = :tagline, description = :description,
                topics = :topics, accent = :accent, question_count = :qc, duration_minutes = :dur, passing_score = :pass,
                price_cents = :price, currency = :cur, is_active = :active, sort_order = :sort WHERE id = :id',
            $params + ['id' => $id]
        );
        return $id;
    }

    public static function questionStats(int $id): array
    {
        return Database::fetchAll(
            'SELECT difficulty, COUNT(*) AS n FROM questions WHERE certification_id = :id AND is_active = 1 GROUP BY difficulty',
            ['id' => $id]
        );
    }
}
