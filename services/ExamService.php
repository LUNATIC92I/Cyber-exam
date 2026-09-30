<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Uuid;
use App\Models\Certification;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\User;

/**
 * Exam engine: attempt creation, randomisation, answer storage, server-side
 * timing and submission. All timing decisions use the server clock.
 */
final class ExamService
{
    /** Small tolerance for answers that were in flight when the clock hit zero. */
    public const GRACE_SECONDS = 10;

    /** Starts (or resumes) an attempt. Requires an unconsumed paid payment. */
    public static function start(array $user, array $certification): array
    {
        $existing = ExamAttempt::activeFor((int) $user['id'], (int) $certification['id']);
        if ($existing) {
            if (self::isExpired($existing)) {
                self::submit($existing, true);
            } else {
                return $existing;
            }
        }
        if (!$certification['is_active']) {
            throw new ExamException('This certification is not currently available.');
        }

        $attemptId = Database::transaction(function () use ($user, $certification) {
            $credit = Payment::availableCredit((int) $user['id'], (int) $certification['id']);
            if (!$credit) {
                throw new ExamException('A confirmed payment is required before starting this exam.');
            }
            // Lock the payment row so two parallel requests cannot consume the same credit.
            $locked = Database::fetch("SELECT id, status FROM payments WHERE id = :id FOR UPDATE", ['id' => $credit['id']]);
            $consumed = Database::value('SELECT id FROM exam_attempts WHERE payment_id = :p', ['p' => $credit['id']]);
            if (!$locked || $locked['status'] !== 'paid' || $consumed) {
                throw new ExamException('This payment has already been used. Please refresh the page.');
            }

            $count = (int) $certification['question_count'];
            $ids = array_map('intval', array_column(Database::fetchAll(
                'SELECT id FROM questions WHERE certification_id = :c AND is_active = 1',
                ['c' => $certification['id']]
            ), 'id'));
            if (count($ids) < $count) {
                throw new ExamException('This exam is temporarily unavailable (question bank incomplete).');
            }
            $selected = array_slice(self::secureShuffle($ids), 0, $count);

            $now = time();
            $attemptId = Database::insert(
                'INSERT INTO exam_attempts (uuid, user_id, certification_id, payment_id, status, total_questions,
                    duration_minutes, passing_score, started_at, expires_at, ip_address)
                 VALUES (:uuid, :u, :c, :p, \'in_progress\', :tq, :dur, :pass, :start, :exp, :ip)',
                [
                    'uuid' => Uuid::v4(), 'u' => $user['id'], 'c' => $certification['id'], 'p' => $credit['id'],
                    'tq' => $count, 'dur' => $certification['duration_minutes'], 'pass' => $certification['passing_score'],
                    'start' => gmdate('Y-m-d H:i:s', $now),
                    'exp' => gmdate('Y-m-d H:i:s', $now + (int) $certification['duration_minutes'] * 60),
                    'ip' => PHP_SAPI === 'cli' ? null : Request::ip(),
                ]
            );

            // Bulk insert the drawn questions with a randomised option order for each.
            foreach (array_chunk($selected, 50, true) as $chunk) {
                $placeholders = [];
                $params = [];
                foreach ($chunk as $i => $qid) {
                    $placeholders[] = '(?, ?, ?, ?)';
                    array_push($params, $attemptId, $qid, $i + 1, implode('', self::secureShuffle(['A', 'B', 'C', 'D'])));
                }
                Database::query('INSERT INTO exam_questions (attempt_id, question_id, position, option_order) VALUES '
                    . implode(',', $placeholders), $params);
            }
            return $attemptId;
        });

        Audit::log('exam.started', 'exam_attempt', $attemptId, ['certification' => $certification['code']]);
        return ExamAttempt::find($attemptId);
    }

    /** Fisher–Yates shuffle driven by the CSPRNG. */
    public static function secureShuffle(array $items): array
    {
        $items = array_values($items);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        return $items;
    }

    public static function remainingSeconds(array $attempt): int
    {
        return max(0, strtotime($attempt['expires_at'] . ' UTC') - time());
    }

    public static function isExpired(array $attempt, int $grace = 0): bool
    {
        return time() >= strtotime($attempt['expires_at'] . ' UTC') + $grace;
    }

    /** Loads an attempt and ensures it belongs to the user. */
    public static function ownedAttempt(string $uuid, int $userId): array
    {
        if (!Uuid::isValid($uuid)) {
            throw new ExamException('Exam not found.', 404);
        }
        $attempt = ExamAttempt::findByUuid($uuid);
        if (!$attempt || (int) $attempt['user_id'] !== $userId) {
            throw new ExamException('Exam not found.', 404);
        }
        return $attempt;
    }

    /** Client payload: questions with shuffled options. Never includes the answer key. */
    public static function state(array $attempt): array
    {
        $questions = [];
        foreach (ExamAttempt::questions((int) $attempt['id']) as $q) {
            $order = str_split($q['option_order']);
            $options = [];
            foreach ($order as $slot => $original) {
                $options[] = ['key' => chr(65 + $slot), 'text' => $q['option_' . strtolower($original)]];
            }
            $selected = null;
            if ($q['selected_option'] !== null) {
                $idx = array_search($q['selected_option'], $order, true);
                $selected = $idx === false ? null : chr(65 + $idx);
            }
            $questions[] = [
                'position' => (int) $q['position'],
                'category' => $q['category'],
                'difficulty' => $q['difficulty'],
                'text' => $q['question'],
                'options' => $options,
                'selected' => $selected,
            ];
        }
        return [
            'attempt' => $attempt['uuid'],
            'status' => $attempt['status'],
            'total' => (int) $attempt['total_questions'],
            'remaining' => self::remainingSeconds($attempt),
            'duration' => (int) $attempt['duration_minutes'] * 60,
            'serverTime' => time(),
            'questions' => $questions,
        ];
    }

    /**
     * Stores (or clears) the answer for one question.
     * $displayed is the letter the candidate clicked (A–D in the shuffled order).
     */
    public static function saveAnswer(array $attempt, int $position, ?string $displayed): array
    {
        if ($attempt['status'] !== 'in_progress') {
            throw new ExamException('This exam has already been submitted.', 409);
        }
        if (self::isExpired($attempt, self::GRACE_SECONDS)) {
            self::submit($attempt, true);
            throw new ExamException('Time is up. Your exam has been submitted automatically.', 410);
        }
        $eq = Database::fetch('SELECT id, option_order FROM exam_questions WHERE attempt_id = :a AND position = :p',
            ['a' => $attempt['id'], 'p' => $position]);
        if (!$eq) {
            throw new ExamException('Invalid question.', 422);
        }
        if ($displayed === null || $displayed === '') {
            Database::query('DELETE FROM answers WHERE exam_question_id = :eq', ['eq' => $eq['id']]);
        } else {
            if (!in_array($displayed, ['A', 'B', 'C', 'D'], true)) {
                throw new ExamException('Invalid answer.', 422);
            }
            $original = $eq['option_order'][ord($displayed) - 65];
            Database::query(
                'INSERT INTO answers (attempt_id, exam_question_id, selected_option) VALUES (:a, :eq, :o)
                 ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), answered_at = CURRENT_TIMESTAMP',
                ['a' => $attempt['id'], 'eq' => $eq['id'], 'o' => $original]
            );
        }
        return [
            'answered' => ExamAttempt::answeredCount((int) $attempt['id']),
            'remaining' => self::remainingSeconds($attempt),
        ];
    }

    /**
     * Finalises an attempt: grades it, stores the result and issues the
     * certificate when passed. Idempotent and safe under concurrency.
     */
    public static function submit(array $attempt, bool $auto = false): array
    {
        $result = Database::transaction(function () use ($attempt, $auto) {
            $locked = ExamAttempt::findForUpdate((int) $attempt['id']);
            if (!$locked || $locked['status'] !== 'in_progress') {
                return ['attempt' => $locked, 'newly' => false];
            }
            $now = time();
            $started = strtotime($locked['started_at'] . ' UTC');
            $expires = strtotime($locked['expires_at'] . ' UTC');
            $expired = $now >= $expires;
            $timeUsed = min($now, $expires) - $started;
            $score = ScoringService::grade($locked);

            Database::query(
                'UPDATE exam_attempts SET status = :st, submitted_at = :sub, time_used_seconds = :tu,
                    correct_count = :cc, score_percent = :sp, passed = :pa WHERE id = :id',
                [
                    'st' => $expired ? 'expired' : 'submitted',
                    'sub' => gmdate('Y-m-d H:i:s', min($now, $expires)),
                    'tu' => max(0, $timeUsed), 'cc' => $score['correct'], 'sp' => $score['percent'],
                    'pa' => $score['passed'] ? 1 : 0, 'id' => $locked['id'],
                ]
            );
            $fresh = ExamAttempt::find((int) $locked['id']);
            $certificate = $score['passed'] ? CertificateService::issue($fresh) : null;
            return ['attempt' => $fresh, 'newly' => true, 'certificate' => $certificate];
        });

        if ($result['newly']) {
            $a = $result['attempt'];
            Audit::log($auto ? 'exam.auto_submitted' : 'exam.submitted', 'exam_attempt', $a['id'],
                ['score' => $a['score_percent'], 'passed' => (bool) $a['passed']], 'user', (int) $a['user_id']);
            self::notify($a, $result['certificate'] ?? null);
        }
        return $result['attempt'];
    }

    private static function notify(array $attempt, ?array $certificate): void
    {
        try {
            $user = User::find((int) $attempt['user_id']);
            $cert = Certification::find((int) $attempt['certification_id']);
            if (!$user || !$cert) {
                return;
            }
            if ($attempt['passed']) {
                Mailer::sendTemplate($user['email'], 'Congratulations! You passed your Cyber Exam certification.', 'exam-passed',
                    ['user' => $user, 'attempt' => $attempt, 'certification' => $cert]);
            } else {
                Mailer::sendTemplate($user['email'], 'Your Cyber Exam result is available.', 'exam-failed',
                    ['user' => $user, 'attempt' => $attempt, 'certification' => $cert]);
            }
            if ($certificate) {
                Mailer::sendTemplate($user['email'], 'Your Cyber Exam certificate is ready.', 'certificate-ready',
                    ['user' => $user, 'certificate' => $certificate, 'certification' => $cert]);
            }
        } catch (\Throwable $e) {
            Logger::error('Result notification failed: ' . $e->getMessage());
        }
    }

    /** Auto-submits every attempt whose time is over (called lazily and by cron). */
    public static function finaliseOverdue(): int
    {
        $n = 0;
        foreach (ExamAttempt::overdue() as $row) {
            $attempt = ExamAttempt::find((int) $row['id']);
            if ($attempt) {
                self::submit($attempt, true);
                $n++;
            }
        }
        return $n;
    }
}
