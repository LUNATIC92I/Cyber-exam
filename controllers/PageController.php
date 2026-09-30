<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Certification;

/** Public marketing pages. */
final class PageController extends Controller
{
    public function home(): never
    {
        $certifications = Certification::active();
        $stats = [
            'certifications' => count($certifications),
            'questions' => (int) Database::value('SELECT COUNT(*) FROM questions WHERE is_active = 1'),
        ];
        $this->render('pages/home', [
            'title' => 'Professional Cybersecurity Certification Exams',
            'certifications' => $certifications,
            'stats' => $stats,
            'bodyClass' => 'page-home',
        ]);
    }

    public function certifications(): never
    {
        $certifications = Certification::active();
        $user = Auth::user();
        $this->render('pages/certifications', [
            'title' => 'Certifications',
            'certifications' => $certifications,
            'user' => $user,
            'access' => $user ? DashboardController::accessMap((int) $user['id'], $certifications) : [],
        ]);
    }
}
