<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Admin;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Question;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CertificateService;

/** Back-office. Every action is gated by AdminAuth::require(<permission>). */
final class AdminController extends Controller
{
    private function page(string $view, array $data): never
    {
        Security::noStore();
        $data['admin'] = AdminAuth::admin();
        $data['noindex'] = true;
        $this->render('admin/' . $view, $data, 'layouts/admin');
    }

    // ------------------------------------------------------------------ auth
    public function login(): never
    {
        if (AdminAuth::admin()) {
            Response::redirect('/admin/index.php');
        }
        if (!Request::isPost()) {
            $this->render('admin/login', ['title' => 'Back-office sign-in', 'noindex' => true], 'layouts/admin-auth');
        }
        $this->requirePost();
        $email = Request::input('email');
        $res = AuthService::attemptAdminLogin($email, Request::raw('password'));
        if (!$res['ok']) {
            $this->back('/admin/login.php', ['email' => $res['error']], ['email' => $email]);
        }
        Response::redirect('/admin/index.php');
    }

    public function logout(): never
    {
        $this->requirePost();
        Audit::log('admin.logout', 'admin', AdminAuth::id());
        AdminAuth::logout();
        Session::regenerate();
        Response::redirect('/admin/login.php');
    }

    /** One-time creation of the first super-admin, protected by ADMIN_SETUP_TOKEN. */
    public function setup(): never
    {
        $configured = (string) Env::get('ADMIN_SETUP_TOKEN', '');
        if ($configured === '' || strlen($configured) < 16 || Admin::count() > 0) {
            Response::abort(404);
        }
        if (!Request::isPost()) {
            $this->render('admin/setup', ['title' => 'Initial setup', 'noindex' => true], 'layouts/admin-auth');
        }
        $this->requirePost();
        \App\Core\RateLimiter::enforce('admin-setup', Request::ip(), 5, 3600);
        if (!hash_equals($configured, Request::raw('setup_token'))) {
            $this->back('/admin/setup.php', ['setup_token' => 'Invalid setup token.']);
        }
        $data = Request::only(['display_name', 'email']) + ['password' => Request::raw('password'), 'password_confirmation' => Request::raw('password_confirmation')];
        $v = Validator::make($data, ['display_name' => 'required|max:120', 'email' => 'required|email|max:191',
            'password' => 'required|password', 'password_confirmation' => 'required|same:password']);
        if ($v->fails()) {
            $this->back('/admin/setup.php', $v->errors());
        }
        $id = Admin::create($data['display_name'], $data['email'], $data['password'], 'super_admin');
        Audit::log('admin.setup_completed', 'admin', $id, [], 'system');
        Session::flash('success', 'Super-administrator created. Remove ADMIN_SETUP_TOKEN from your .env file now.');
        Response::redirect('/admin/login.php');
    }

    // ------------------------------------------------------------------ dashboard
    public function dashboard(): never
    {
        AdminAuth::require('dashboard.view');
        $kpi = [
            'users' => (int) Database::value('SELECT COUNT(*) FROM users'),
            'exams' => (int) Database::value("SELECT COUNT(*) FROM exam_attempts WHERE status <> 'in_progress'"),
            'in_progress' => (int) Database::value("SELECT COUNT(*) FROM exam_attempts WHERE status = 'in_progress'"),
            'payments' => (int) Database::value("SELECT COUNT(*) FROM payments WHERE status = 'paid'"),
            'revenue' => (int) Database::value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid'"),
            'certificates' => (int) Database::value("SELECT COUNT(*) FROM certificates WHERE status = 'valid'"),
            'questions' => (int) Database::value('SELECT COUNT(*) FROM questions WHERE is_active = 1'),
            'certifications' => (int) Database::value('SELECT COUNT(*) FROM certifications WHERE is_active = 1'),
            'pass_rate' => (float) Database::value("SELECT COALESCE(AVG(passed) * 100, 0) FROM exam_attempts WHERE status <> 'in_progress'"),
        ];
        $revenue = Database::fetchAll(
            "SELECT DATE(paid_at) AS d, SUM(amount) AS total, COUNT(*) AS n FROM payments
             WHERE status = 'paid' AND paid_at >= :since GROUP BY DATE(paid_at) ORDER BY d",
            ['since' => gmdate('Y-m-d 00:00:00', strtotime('-29 days'))]
        );
        $byDay = array_column($revenue, 'total', 'd');
        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', strtotime("-{$i} days"));
            $series[] = ['label' => gmdate('j M', strtotime($d)), 'value' => (int) ($byDay[$d] ?? 0)];
        }
        $this->page('dashboard', [
            'title' => 'Overview',
            'kpi' => $kpi,
            'series' => $series,
            'perCert' => Database::fetchAll(
                "SELECT c.name, COUNT(a.id) AS attempts, COALESCE(SUM(a.passed = 1), 0) AS passed, AVG(a.score_percent) AS avg_score
                 FROM certifications c LEFT JOIN exam_attempts a ON a.certification_id = c.id AND a.status <> 'in_progress'
                 GROUP BY c.id ORDER BY c.sort_order"
            ),
            'recent' => Database::fetchAll(
                "SELECT action, actor_type, entity_type, entity_id, created_at FROM audit_logs ORDER BY id DESC LIMIT 12"
            ),
        ]);
    }

    // ------------------------------------------------------------------ users
    public function users(): never
    {
        $admin = AdminAuth::require('users.view');
        if (Request::isPost()) {
            AdminAuth::require('users.manage');
            $this->requirePost();
            $user = User::find(Request::int('user_id'));
            if ($user) {
                $action = Request::input('do');
                if ($action === 'suspend' || $action === 'activate') {
                    User::setStatus((int) $user['id'], $action === 'suspend' ? 'suspended' : 'active');
                    Audit::log('admin.user_' . $action, 'user', $user['id']);
                    Session::flash('success', 'Account ' . ($action === 'suspend' ? 'suspended' : 'reactivated') . '.');
                } elseif ($action === 'verify_email') {
                    User::markEmailVerified((int) $user['id']);
                    Audit::log('admin.user_email_verified', 'user', $user['id']);
                    Session::flash('success', 'Email marked as verified.');
                }
            }
            Response::redirect('/admin/users.php' . ($user ? '?id=' . (int) $user['id'] : ''));
        }
        if (($id = Request::int('id')) > 0 && ($user = User::find($id))) {
            $this->page('user', [
                'title' => User::fullName($user), 'user' => $user, 'stats' => User::stats($id),
                'attempts' => ExamAttempt::historyForUser($id, 50), 'payments' => Payment::forUser($id),
                'certificates' => Certificate::forUser($id), 'canManage' => AdminAuth::can('users.manage', $admin),
            ]);
        }
        $search = Request::query('q');
        $this->page('users', ['title' => 'Candidates', 'search' => $search,
            'list' => User::paginate($search, max(1, Request::int('page', 1)))]);
    }

    // ------------------------------------------------------------------ certifications
    public function certifications(): never
    {
        $admin = AdminAuth::require('certifications.view');
        $editId = Request::int('edit');
        if (Request::isPost()) {
            AdminAuth::require('certifications.manage');
            $this->requirePost();
            $id = Request::int('id') ?: null;
            $data = Request::only(['code', 'slug', 'name', 'tagline', 'description', 'accent', 'currency']);
            $data['code'] = strtoupper($data['code']);
            $data['topics'] = array_values(array_filter(array_map('trim', explode(',', Request::input('topics')))));
            $data['question_count'] = Request::input('question_count', '100');
            $data['duration_minutes'] = Request::input('duration_minutes', '120');
            $data['passing_score'] = Request::input('passing_score', '70');
            $data['price'] = Request::input('price', '10.00');
            $data['sort_order'] = Request::input('sort_order', '0');
            $data['is_active'] = Request::input('is_active') === '1' ? 1 : 0;
            $v = Validator::make($data, [
                'code' => 'required|regex:/^[A-Z0-9]{2,10}$/', 'slug' => 'required|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|max:80',
                'name' => 'required|max:120', 'tagline' => 'max:255', 'description' => 'max:5000',
                'accent' => 'required|in:' . implode(',', Certification::ACCENTS), 'currency' => 'required|in:EUR,USD,GBP,XAF,XOF',
                'question_count' => 'required|between:10,200', 'duration_minutes' => 'required|between:10,600',
                'passing_score' => 'required|between:1,100', 'price' => 'required|regex:/^\d{1,6}(\.\d{1,2})?$/',
                'sort_order' => 'required|between:-1000,1000',
            ]);
            $errors = $v->errors();
            $dupe = Database::fetch('SELECT id FROM certifications WHERE (code = :c OR slug = :s) AND id <> :id',
                ['c' => $data['code'], 's' => $data['slug'], 'id' => $id ?? 0]);
            if ($dupe) {
                $errors['code'] = 'Code and slug must be unique.';
            }
            if ($data['is_active'] && !isset($errors['question_count'])) {
                $bank = $id ? (int) Database::value('SELECT COUNT(*) FROM questions WHERE certification_id = :c AND is_active = 1', ['c' => $id]) : 0;
                if ($bank < (int) $data['question_count']) {
                    $errors['is_active'] = "Cannot activate: the question bank has {$bank} active questions, {$data['question_count']} required.";
                }
            }
            if ($errors) {
                $this->back('/admin/certifications.php?edit=' . ($id ?? 'new'), $errors);
            }
            $data['price_cents'] = (int) round((float) $data['price'] * 100);
            $savedId = Certification::save($data, $id);
            Audit::log($id ? 'admin.certification_updated' : 'admin.certification_created', 'certification', $savedId,
                ['price_cents' => $data['price_cents'], 'duration' => (int) $data['duration_minutes'], 'active' => $data['is_active']]);
            Session::flash('success', 'Certification saved.');
            Response::redirect('/admin/certifications.php');
        }
        if ($editId > 0 || Request::query('edit') === 'new') {
            $cert = $editId > 0 ? Certification::find($editId) : null;
            if ($editId > 0 && !$cert) {
                Response::abort(404);
            }
            AdminAuth::require('certifications.manage');
            $this->page('certification-form', ['title' => $cert ? 'Edit ' . $cert['name'] : 'New certification', 'cert' => $cert,
                'stats' => $cert ? Certification::questionStats((int) $cert['id']) : []]);
        }
        $this->page('certifications', ['title' => 'Certifications', 'certifications' => Certification::all(),
            'canManage' => AdminAuth::can('certifications.manage', $admin)]);
    }

    // ------------------------------------------------------------------ questions
    public function questions(): never
    {
        $admin = AdminAuth::require('questions.view');
        if (Request::isPost()) {
            AdminAuth::require('questions.manage');
            $this->requirePost();
            if (Request::input('do') === 'delete') {
                $id = Request::int('id');
                if (Question::find($id)) {
                    $outcome = Question::delete($id);
                    Audit::log('admin.question_' . $outcome, 'question', $id);
                    Session::flash('success', $outcome === 'deleted' ? 'Question deleted.' : 'Question already used in exams: it was archived instead of deleted.');
                }
                Response::redirect('/admin/questions.php');
            }
            $id = Request::int('id') ?: null;
            $data = Request::only(['certification_id', 'category', 'difficulty', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation']);
            $data['is_active'] = Request::input('is_active') === '1' ? '1' : '0';
            $v = Validator::make($data, [
                'certification_id' => 'required|int', 'category' => 'required|max:80',
                'difficulty' => 'required|in:beginner,intermediate,advanced', 'question' => 'required|min:15|max:4000',
                'option_a' => 'required|max:500', 'option_b' => 'required|max:500', 'option_c' => 'required|max:500', 'option_d' => 'required|max:500',
                'correct_option' => 'required|in:A,B,C,D', 'explanation' => 'required|min:10|max:4000',
            ]);
            $errors = $v->errors();
            if (!$errors && !Certification::find((int) $data['certification_id'])) {
                $errors['certification_id'] = 'Unknown certification.';
            }
            $opts = array_map('mb_strtolower', [$data['option_a'], $data['option_b'], $data['option_c'], $data['option_d']]);
            if (!$errors && count(array_unique($opts)) < 4) {
                $errors['option_a'] = 'The four options must be different.';
            }
            if (!$errors && Database::value('SELECT id FROM questions WHERE certification_id = :c AND content_hash = :h AND id <> :id',
                    ['c' => (int) $data['certification_id'], 'h' => Question::hash($data['question']), 'id' => $id ?? 0])) {
                $errors['question'] = 'An identical question already exists for this certification.';
            }
            if ($errors) {
                $this->back('/admin/questions.php?edit=' . ($id ?? 'new'), $errors);
            }
            $savedId = Question::save($data, $id);
            Audit::log($id ? 'admin.question_updated' : 'admin.question_created', 'question', $savedId);
            Session::flash('success', 'Question saved.');
            Response::redirect('/admin/questions.php?certification_id=' . (int) $data['certification_id']);
        }
        $editId = Request::int('edit');
        if ($editId > 0 || Request::query('edit') === 'new') {
            AdminAuth::require('questions.manage');
            $q = $editId > 0 ? Question::find($editId) : null;
            if ($editId > 0 && !$q) {
                Response::abort(404);
            }
            $this->page('question-form', ['title' => $q ? 'Edit question #' . $q['id'] : 'New question', 'q' => $q,
                'certifications' => Certification::all(), 'preset' => Request::int('certification_id')]);
        }
        $filters = ['certification_id' => Request::int('certification_id'), 'difficulty' => Request::query('difficulty'),
            'search' => Request::query('q'), 'status' => Request::query('status')];
        $this->page('questions', ['title' => 'Question bank', 'filters' => $filters, 'certifications' => Certification::all(),
            'list' => Question::paginate($filters, max(1, Request::int('page', 1))), 'canManage' => AdminAuth::can('questions.manage', $admin)]);
    }

    // ------------------------------------------------------------------ exams
    public function exams(): never
    {
        $admin = AdminAuth::require('exams.view');
        if (Request::isPost()) {
            AdminAuth::require('certificates.manage');
            $this->requirePost();
            $attempt = ExamAttempt::find(Request::int('attempt_id'));
            if ($attempt && (int) $attempt['passed'] === 1 && !Certificate::findByAttempt((int) $attempt['id'])) {
                $c = CertificateService::issue($attempt);
                Audit::log('admin.certificate_generated', 'certificate', $c['certificate_code']);
                Session::flash('success', 'Certificate ' . $c['certificate_code'] . ' generated.');
            } else {
                Session::flash('error', 'A certificate can only be generated for a passed exam without certificate.');
            }
            Response::redirect('/admin/exams.php');
        }
        \App\Services\ExamService::finaliseOverdue();
        $filters = ['certification_id' => Request::int('certification_id'), 'result' => Request::query('result'), 'search' => Request::query('q')];
        $this->page('exams', ['title' => 'Exams & results', 'filters' => $filters, 'certifications' => Certification::all(),
            'list' => ExamAttempt::paginate($filters, max(1, Request::int('page', 1))), 'canIssue' => AdminAuth::can('certificates.manage', $admin)]);
    }

    // ------------------------------------------------------------------ payments
    public function payments(): never
    {
        AdminAuth::require('payments.view');
        $filters = ['status' => Request::query('status'), 'search' => Request::query('q')];
        $this->page('payments', ['title' => 'Payments', 'filters' => $filters,
            'list' => Payment::paginate($filters, max(1, Request::int('page', 1))),
            'revenue' => (int) Database::value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'")]);
    }

    // ------------------------------------------------------------------ certificates
    public function certificates(): never
    {
        $admin = AdminAuth::require('certificates.view');
        if (Request::isPost()) {
            AdminAuth::require('certificates.manage');
            $this->requirePost();
            $code = Certificate::normaliseCode(Request::input('code'));
            if (Request::input('do') === 'revoke') {
                $reason = Request::input('reason');
                if (mb_strlen($reason) < 5) {
                    Session::flash('error', 'Please give a revocation reason (min. 5 characters).');
                } else {
                    $ok = CertificateService::revoke($code, $reason);
                    Session::flash($ok ? 'success' : 'error', $ok ? "Certificate {$code} revoked." : 'Certificate not found or already revoked.');
                }
            } elseif (Request::input('do') === 'reinstate') {
                $ok = CertificateService::reinstate($code);
                Session::flash($ok ? 'success' : 'error', $ok ? "Certificate {$code} reinstated." : 'Certificate not found or not revoked.');
            }
            Response::redirect('/admin/certificates.php?q=' . urlencode($code));
        }
        $filters = ['status' => Request::query('status'), 'search' => Request::query('q')];
        $this->page('certificates', ['title' => 'Certificates', 'filters' => $filters,
            'list' => Certificate::paginate($filters, max(1, Request::int('page', 1))), 'canManage' => AdminAuth::can('certificates.manage', $admin)]);
    }

    // ------------------------------------------------------------------ settings
    public function settings(): never
    {
        $admin = AdminAuth::require('settings.manage');
        if (Request::isPost()) {
            $this->requirePost();
            if (Request::input('do') === 'add_admin') {
                $data = Request::only(['display_name', 'email', 'role']) + ['password' => Request::raw('password')];
                $v = Validator::make($data, ['display_name' => 'required|max:120', 'email' => 'required|email|max:191',
                    'role' => 'required|in:' . implode(',', array_keys(Admin::ROLES)), 'password' => 'required|password']);
                $errors = $v->errors();
                if (!$errors && Admin::findByEmail($data['email'])) {
                    $errors['email'] = 'An administrator with this email already exists.';
                }
                if ($errors) {
                    $this->back('/admin/settings.php', $errors);
                }
                $id = Admin::create($data['display_name'], $data['email'], $data['password'], $data['role']);
                Audit::log('admin.admin_created', 'admin', $id, ['role' => $data['role']]);
                Session::flash('success', 'Administrator account created.');
                Response::redirect('/admin/settings.php');
            }
            if (Request::input('do') === 'toggle_admin') {
                $target = Request::int('admin_id');
                if ($target === (int) $admin['id']) {
                    Session::flash('error', 'You cannot disable your own account.');
                } else {
                    Database::query("UPDATE admins SET status = IF(status = 'active', 'disabled', 'active') WHERE id = :id", ['id' => $target]);
                    Audit::log('admin.admin_toggled', 'admin', $target);
                    Session::flash('success', 'Administrator status updated.');
                }
                Response::redirect('/admin/settings.php');
            }
            $values = [];
            foreach (array_keys(Setting::SCHEMA) as $key) {
                $values[$key] = Request::input($key);
            }
            $values['require_email_verification'] = Request::input('require_email_verification') === '1' ? '1' : '0';
            $values['registration_open'] = Request::input('registration_open') === '1' ? '1' : '0';
            $values['default_price_cents'] = (string) (int) round((float) Request::input('default_price', '10') * 100);
            $v = Validator::make($values, Setting::SCHEMA);
            if ($v->fails()) {
                $this->back('/admin/settings.php', $v->errors());
            }
            Database::transaction(function () use ($values) {
                foreach ($values as $k => $val) {
                    Setting::set($k, $val);
                }
                if (Request::input('apply_all') === '1') {
                    Database::query('UPDATE certifications SET duration_minutes = :d, price_cents = :p, currency = :c, passing_score = :s, question_count = :q',
                        ['d' => (int) $values['default_duration_minutes'], 'p' => (int) $values['default_price_cents'],
                         'c' => $values['default_currency'], 's' => (int) $values['passing_score'], 'q' => (int) $values['questions_per_exam']]);
                }
            });
            Audit::log('admin.settings_updated', 'settings', null, ['apply_all' => Request::input('apply_all') === '1'] + $values);
            Session::flash('success', 'Settings saved' . (Request::input('apply_all') === '1' ? ' and applied to all certifications.' : '.'));
            Response::redirect('/admin/settings.php');
        }
        Setting::flush();
        $this->page('settings', ['title' => 'Settings', 'settings' => Setting::all(), 'admins' => Admin::all(),
            'audit' => Database::fetchAll('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 30')]);
    }
}
