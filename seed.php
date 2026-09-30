<?php
declare(strict_types=1);

/**
 * CYBER EXAM — seeder (CLI only).
 *
 *   php seed.php                 Seed/refresh certifications and question banks (idempotent)
 *   php seed.php --check         Validate the question banks without touching the database
 *
 * Question bank format (database/seeds/questions/<file>.php):
 *   [category, difficulty(B|I|A), question, [optionA, optionB, optionC, optionD], correctIndex(0-3), explanation]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/includes/bootstrap.php';

use App\Core\Database;
use App\Models\Question;

/** Loads and validates every question bank. Returns [certs, errors]. */
function cex_load_banks(): array
{
    $certs = require __DIR__ . '/database/seeds/certifications.php';
    $errors = [];
    $difficulties = ['B' => 'beginner', 'I' => 'intermediate', 'A' => 'advanced'];
    foreach ($certs as $ci => $cert) {
        $file = __DIR__ . '/database/seeds/questions/' . $cert['file'] . '.php';
        $bank = is_file($file) ? require $file : [];
        $seen = [];
        $questions = [];
        foreach ($bank as $i => $row) {
            $ref = "{$cert['code']}#" . ($i + 1);
            if (!is_array($row) || count($row) !== 6) {
                $errors[] = "{$ref}: expected 6 fields";
                continue;
            }
            [$category, $diff, $q, $opts, $correct, $explanation] = $row;
            if (!isset($difficulties[$diff])) {
                $errors[] = "{$ref}: invalid difficulty {$diff}";
            }
            if (!is_array($opts) || count($opts) !== 4 || count(array_unique(array_map('mb_strtolower', $opts))) !== 4) {
                $errors[] = "{$ref}: needs 4 distinct options";
            }
            if (!is_int($correct) || $correct < 0 || $correct > 3) {
                $errors[] = "{$ref}: invalid correct index";
            }
            if (mb_strlen(trim((string) $q)) < 15 || mb_strlen(trim((string) $explanation)) < 15) {
                $errors[] = "{$ref}: question/explanation too short";
            }
            foreach ((array) $opts as $o) {
                if (mb_strlen((string) $o) > 500) {
                    $errors[] = "{$ref}: option longer than 500 chars";
                }
            }
            $h = Question::hash((string) $q);
            if (isset($seen[$h])) {
                $errors[] = "{$ref}: duplicate of #{$seen[$h]}";
            }
            $seen[$h] = $i + 1;
            // Balance the stored position of the correct answer (deterministic per question).
            // Candidates never see this order: options are re-shuffled for every exam attempt.
            if (is_array($opts) && count($opts) === 4 && is_int($correct) && $correct >= 0 && $correct <= 3) {
                $target = hexdec(substr($h, 0, 7)) % 4;
                [$opts[$correct], $opts[$target]] = [$opts[$target], $opts[$correct]];
                $correct = $target;
            }
            $questions[] = [
                'category' => $category, 'difficulty' => $difficulties[$diff] ?? 'intermediate', 'question' => $q,
                'option_a' => $opts[0] ?? '', 'option_b' => $opts[1] ?? '', 'option_c' => $opts[2] ?? '', 'option_d' => $opts[3] ?? '',
                'correct_option' => ['A', 'B', 'C', 'D'][$correct] ?? 'A', 'explanation' => $explanation,
            ];
        }
        // A certification whose bank has fewer than 100 questions is seeded as INACTIVE;
        // administrators can complete it from /admin/questions.php and then activate it.
        $certs[$ci]['complete'] = count($questions) >= 100;
        $certs[$ci]['questions'] = $questions;
    }
    return [$certs, $errors];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return; // included as a library (tools/build-sql.php, tests)
}

[$certs, $errors] = cex_load_banks();
$total = array_sum(array_map(fn ($c) => count($c['questions']), $certs));

if (in_array('--check', $argv, true)) {
    foreach ($certs as $c) {
        $dist = array_count_values(array_column($c['questions'], 'correct_option'));
        $diff = array_count_values(array_column($c['questions'], 'difficulty'));
        ksort($dist);
        printf("%-7s %3d questions | answers %s | %s\n", $c['code'], count($c['questions']),
            json_encode($dist), json_encode($diff));
    }
    echo "Total: {$total} questions\n";
    echo $errors ? "ERRORS:\n - " . implode("\n - ", $errors) . "\n" : "All banks valid.\n";
    exit($errors ? 1 : 0);
}

if ($errors) {
    fwrite(STDERR, "Question banks contain errors:\n - " . implode("\n - ", $errors) . "\n");
    exit(1);
}

$defaults = [
    'duration' => (int) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'default_duration_minutes'") ?? 120),
    'price' => (int) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'default_price_cents'") ?? 1000),
    'pass' => (int) (Database::value("SELECT setting_value FROM settings WHERE setting_key = 'passing_score'") ?? 70),
];

$inserted = 0;
Database::transaction(function () use ($certs, $defaults, &$inserted) {
    foreach ($certs as $order => $c) {
        $id = Database::value('SELECT id FROM certifications WHERE code = :c', ['c' => $c['code']]);
        if (!$id) {
            $id = Database::insert(
                'INSERT INTO certifications (code, slug, name, tagline, description, topics, accent, question_count,
                    duration_minutes, passing_score, price_cents, currency, is_active, sort_order)
                 VALUES (:code, :slug, :name, :tagline, :description, :topics, :accent, 100, :dur, :pass, :price, \'EUR\', :active, :sort)',
                ['code' => $c['code'], 'slug' => $c['slug'], 'name' => $c['name'], 'tagline' => $c['tagline'],
                 'description' => $c['description'], 'topics' => json_encode($c['topics'], JSON_UNESCAPED_UNICODE),
                 'accent' => $c['accent'], 'dur' => $defaults['duration'], 'pass' => $defaults['pass'],
                 'price' => $defaults['price'], 'active' => $c['complete'] ? 1 : 0, 'sort' => ($order + 1) * 10]
            );
            echo "  + certification {$c['code']}" . ($c['complete'] ? '' : ' (inactive: question bank incomplete)') . "\n";
        }
        foreach ($c['questions'] as $q) {
            $stmt = Database::query(
                'INSERT IGNORE INTO questions (certification_id, category, difficulty, question, option_a, option_b, option_c,
                    option_d, correct_option, explanation, content_hash)
                 VALUES (:cid, :cat, :dif, :q, :a, :b, :c, :d, :co, :ex, :h)',
                ['cid' => (int) $id, 'cat' => $q['category'], 'dif' => $q['difficulty'], 'q' => $q['question'],
                 'a' => $q['option_a'], 'b' => $q['option_b'], 'c' => $q['option_c'], 'd' => $q['option_d'],
                 'co' => $q['correct_option'], 'ex' => $q['explanation'], 'h' => Question::hash($q['question'])]
            );
            $inserted += $stmt->rowCount();
        }
    }
});

echo "Seed complete: " . count($certs) . " certifications, {$total} questions in banks, {$inserted} new questions inserted.\n";
