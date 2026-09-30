<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Exam scoring. 1 point per question; pass when score >= passing threshold.
 * Pass/fail uses integer arithmetic (correct*100 >= passing*total) so there is
 * no floating point ambiguity at the boundary (69 % fails, 70 % passes).
 */
final class ScoringService
{
    public static function evaluate(int $correct, int $total, int $passingScore): array
    {
        if ($total <= 0) {
            return ['correct' => 0, 'incorrect' => 0, 'total' => 0, 'percent' => 0.0, 'passed' => false];
        }
        $correct = max(0, min($correct, $total));
        return [
            'correct'   => $correct,
            'incorrect' => $total - $correct,
            'total'     => $total,
            'percent'   => round($correct * 100 / $total, 2),
            'passed'    => $correct * 100 >= $passingScore * $total,
        ];
    }

    /**
     * Grades every question of an attempt against the answer key held in the
     * database (never trusting anything from the client) and flags each answer.
     */
    public static function grade(array $attempt): array
    {
        $attemptId = (int) $attempt['id'];
        Database::query(
            'UPDATE answers ans
             JOIN exam_questions eq ON eq.id = ans.exam_question_id
             JOIN questions q ON q.id = eq.question_id
             SET ans.is_correct = (ans.selected_option = q.correct_option)
             WHERE ans.attempt_id = :a',
            ['a' => $attemptId]
        );
        $correct = (int) Database::value('SELECT COUNT(*) FROM answers WHERE attempt_id = :a AND is_correct = 1', ['a' => $attemptId]);
        return self::evaluate($correct, (int) $attempt['total_questions'], (int) $attempt['passing_score']);
    }

    /** Per-category breakdown for the result page. */
    public static function breakdown(int $attemptId): array
    {
        return Database::fetchAll(
            'SELECT q.category, COUNT(*) AS total, COALESCE(SUM(ans.is_correct = 1), 0) AS correct
             FROM exam_questions eq
             JOIN questions q ON q.id = eq.question_id
             LEFT JOIN answers ans ON ans.exam_question_id = eq.id
             WHERE eq.attempt_id = :a GROUP BY q.category ORDER BY q.category',
            ['a' => $attemptId]
        );
    }
}
