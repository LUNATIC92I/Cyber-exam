<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Security;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExamService;

final class DashboardController extends Controller
{
    public function index(): never
    {
        $user = Auth::require();
        Security::noStore();
        ExamService::finaliseOverdue();

        $certifications = Certification::active();
        $history = ExamAttempt::historyForUser((int) $user['id'], 12);
        $this->render('pages/dashboard', [
            'title' => 'Dashboard',
            'user' => $user,
            'stats' => User::stats((int) $user['id']),
            'certifications' => $certifications,
            'access' => self::accessMap((int) $user['id'], $certifications),
            'history' => $history,
            'certificates' => Certificate::forUser((int) $user['id']),
            'payments' => Payment::forUser((int) $user['id']),
            'needsVerification' => Setting::bool('require_email_verification', true) && !$user['email_verified_at'],
            'chart' => array_reverse(array_map(fn ($a) => [
                'label' => $a['certification_code'], 'score' => (float) $a['score_percent'], 'passed' => (bool) $a['passed'],
            ], array_slice($history, 0, 8))),
        ]);
    }

    /**
     * Access state per certification for a user:
     *  resume (attempt in progress) | start (paid credit available) | pay
     */
    public static function accessMap(int $userId, array $certifications): array
    {
        $map = [];
        $active = [];
        foreach (ExamAttempt::activeForUser($userId) as $a) {
            $active[(int) $a['certification_id']] = $a;
        }
        foreach ($certifications as $c) {
            $id = (int) $c['id'];
            if (isset($active[$id]) && !ExamService::isExpired($active[$id])) {
                $map[$id] = ['state' => 'resume', 'attempt' => $active[$id]['uuid']];
            } elseif (Payment::availableCredit($userId, $id)) {
                $map[$id] = ['state' => 'start'];
            } else {
                $map[$id] = ['state' => 'pay'];
            }
        }
        return $map;
    }
}
