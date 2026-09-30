<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Models\Certificate;
use App\Models\Certification;
use App\Services\ExamException;
use App\Services\ExamService;
use App\Services\ScoringService;

final class ResultController extends Controller
{
    public function show(): never
    {
        $user = Auth::require();
        Security::noStore();
        try {
            $attempt = ExamService::ownedAttempt(Request::query('attempt'), (int) $user['id']);
        } catch (ExamException) {
            Response::abort(404, 'Result not found.');
        }
        if ($attempt['status'] === 'in_progress') {
            if (!ExamService::isExpired($attempt)) {
                Response::redirect('/exam.php?attempt=' . $attempt['uuid']);
            }
            $attempt = ExamService::submit($attempt, true);
        }
        $this->render('pages/result', [
            'title' => 'Exam result',
            'user' => $user,
            'attempt' => $attempt,
            'certification' => Certification::find((int) $attempt['certification_id']),
            'certificate' => Certificate::findByAttempt((int) $attempt['id']),
            'breakdown' => ScoringService::breakdown((int) $attempt['id']),
            'answered' => (int) \App\Core\Database::value('SELECT COUNT(*) FROM answers WHERE attempt_id = :a', ['a' => $attempt['id']]),
        ]);
    }
}
