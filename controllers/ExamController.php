<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Models\Certification;
use App\Models\Setting;
use App\Services\ExamException;
use App\Services\ExamService;

final class ExamController extends Controller
{
    /** GET: exam room for an attempt · POST: start an exam for a certification. */
    public function handle(): never
    {
        $user = Auth::require();
        Security::noStore();

        if (Request::isPost()) {
            $this->requirePost();
            $cert = Certification::find(Request::int('certification_id'));
            if (!$cert) {
                Response::abort(404, 'Certification not found.');
            }
            if (Setting::bool('require_email_verification', true) && !$user['email_verified_at']) {
                Session::flash('error', 'Please confirm your email address first.');
                Response::redirect('/dashboard.php');
            }
            RateLimiter::enforce('exam-start:user', (string) $user['id'], 20, 3600);
            try {
                $attempt = ExamService::start($user, $cert);
            } catch (ExamException $e) {
                Session::flash('error', $e->getMessage());
                Response::redirect('/checkout.php?certification=' . urlencode($cert['slug']));
            }
            Response::redirect('/exam.php?attempt=' . $attempt['uuid']);
        }

        try {
            $attempt = ExamService::ownedAttempt(Request::query('attempt'), (int) $user['id']);
        } catch (ExamException $e) {
            Response::abort(404, 'Exam not found.');
        }
        if ($attempt['status'] === 'in_progress' && ExamService::isExpired($attempt)) {
            $attempt = ExamService::submit($attempt, true);
        }
        if ($attempt['status'] !== 'in_progress') {
            Response::redirect('/result.php?attempt=' . $attempt['uuid']);
        }
        $this->render('pages/exam', [
            'title' => 'Exam in progress',
            'attempt' => $attempt,
            'certification' => Certification::find((int) $attempt['certification_id']),
            'bodyClass' => 'page-exam',
        ], 'layouts/exam');
    }

    /** JSON API used by the exam room (state, answer, submit, sync). */
    public function api(): never
    {
        $user = Auth::user();
        if (!$user) {
            Response::json(['error' => 'Your session has expired. Please sign in again.'], 401);
        }
        Security::noStore();
        $action = Request::query('action');
        $body = Request::isPost() ? Request::json() : [];
        if (Request::isPost()) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!Csrf::validate(is_string($token) ? $token : null)) {
                Response::json(['error' => 'Invalid security token. Please reload the page.'], 419);
            }
        }
        if (!RateLimiter::hit('exam-api:user', (string) $user['id'], 600, 60)) {
            Response::json(['error' => 'Too many requests. Please slow down.'], 429);
        }

        try {
            $uuid = (string) ($body['attempt'] ?? Request::query('attempt'));
            $attempt = ExamService::ownedAttempt($uuid, (int) $user['id']);

            switch ($action) {
                case 'state':
                    if ($attempt['status'] === 'in_progress' && ExamService::isExpired($attempt)) {
                        $attempt = ExamService::submit($attempt, true);
                    }
                    if ($attempt['status'] !== 'in_progress') {
                        Response::json(['finished' => true, 'redirect' => url('result.php', ['attempt' => $attempt['uuid']])]);
                    }
                    Response::json(ExamService::state($attempt));

                case 'sync':
                    if ($attempt['status'] === 'in_progress' && ExamService::isExpired($attempt)) {
                        $attempt = ExamService::submit($attempt, true);
                    }
                    Response::json([
                        'status' => $attempt['status'],
                        'remaining' => ExamService::remainingSeconds($attempt),
                        'redirect' => $attempt['status'] !== 'in_progress' ? url('result.php', ['attempt' => $attempt['uuid']]) : null,
                    ]);

                case 'answer':
                    if (!Request::isPost()) {
                        Response::json(['error' => 'Method not allowed'], 405);
                    }
                    $position = filter_var($body['position'] ?? null, FILTER_VALIDATE_INT);
                    $choice = $body['choice'] ?? null;
                    if ($position === false || ($choice !== null && !is_string($choice))) {
                        Response::json(['error' => 'Invalid request.'], 422);
                    }
                    Response::json(['ok' => true] + ExamService::saveAnswer($attempt, (int) $position, $choice));

                case 'submit':
                    if (!Request::isPost()) {
                        Response::json(['error' => 'Method not allowed'], 405);
                    }
                    $final = ExamService::submit($attempt, (bool) ($body['auto'] ?? false));
                    Response::json(['ok' => true, 'redirect' => url('result.php', ['attempt' => $final['uuid']])]);
            }
            Response::json(['error' => 'Unknown action.'], 400);
        } catch (ExamException $e) {
            $payload = ['error' => $e->getMessage()];
            if ($e->getCode() === 410 && isset($attempt)) {
                $payload['redirect'] = url('result.php', ['attempt' => $attempt['uuid']]);
            }
            Response::json($payload, $e->getCode() ?: 400);
        }
    }
}
