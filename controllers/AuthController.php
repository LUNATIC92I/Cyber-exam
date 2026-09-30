<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function register(): never
    {
        if (Auth::check()) {
            Response::redirect('/dashboard.php');
        }
        if (!Request::isPost()) {
            $this->render('auth/register', ['title' => 'Create your account', 'open' => Setting::bool('registration_open', true)]);
        }
        $this->requirePost();
        if (!Setting::bool('registration_open', true)) {
            Response::abort(403, 'Registrations are temporarily closed.');
        }
        RateLimiter::enforce('register:ip', Request::ip(), 10, 3600);

        $data = Request::only(['first_name', 'last_name', 'email', 'country']);
        $data['email'] = mb_strtolower($data['email']);
        $data['password'] = Request::raw('password');
        $data['password_confirmation'] = Request::raw('password_confirmation');

        $v = Validator::make($data, [
            'first_name' => 'required|max:80|name',
            'last_name' => 'required|max:80|name',
            'email' => 'required|email|max:191',
            'country' => 'required|country',
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
        ]);
        $errors = $v->errors();
        if (!isset($errors['email']) && User::findByEmail($data['email'])) {
            // Generic wording to limit account enumeration.
            $errors['email'] = 'This email cannot be used. If you already have an account, sign in or reset your password.';
        }
        if (Request::input('terms') !== '1') {
            $errors['terms'] = 'You must accept the exam rules and terms.';
        }
        if ($errors) {
            $this->back('/register.php', $errors);
        }

        $user = AuthService::register($data);
        Auth::login($user);
        Session::flash('success', 'Welcome to CYBER EXAM! We sent a confirmation link to ' . $user['email'] . '.');
        Response::redirect('/dashboard.php');
    }

    public function login(): never
    {
        if (Auth::check()) {
            Response::redirect('/dashboard.php');
        }
        if (!Request::isPost()) {
            $this->render('auth/login', ['title' => 'Sign in']);
        }
        $this->requirePost();
        $email = Request::input('email');
        $result = AuthService::attemptLogin($email, Request::raw('password'));
        if (!$result['ok']) {
            $this->back('/login.php', ['email' => $result['error']], ['email' => $email]);
        }
        $target = $this->safeRedirectTarget(Session::get('intended'), '/dashboard.php');
        Session::forget('intended');
        Response::redirect($target);
    }

    public function logout(): never
    {
        $this->requirePost();
        if (Auth::id()) {
            Audit::log('auth.logout', 'user', Auth::id());
        }
        Session::destroy();
        Session::start();
        Session::flash('success', 'You have been signed out.');
        Response::redirect('/login.php');
    }

    public function verifyEmail(): never
    {
        $token = Request::query('token');
        $user = $token !== '' ? AuthService::verifyEmail($token) : null;
        if ($user) {
            Session::flash('success', 'Your email address is confirmed. You can now purchase and take exams.');
            Auth::refresh();
            Response::redirect(Auth::check() ? '/dashboard.php' : '/login.php');
        }
        $this->render('auth/message', [
            'title' => 'Link invalid or expired',
            'heading' => 'This confirmation link is invalid or has expired',
            'text' => 'Sign in and request a new confirmation email from your dashboard.',
        ]);
    }

    public function resendVerification(): never
    {
        $this->requirePost();
        $user = Auth::require();
        RateLimiter::enforce('verify-resend', (string) $user['id'], 3, 3600);
        if (!$user['email_verified_at']) {
            AuthService::sendVerification($user);
        }
        Session::flash('success', 'A new confirmation email is on its way.');
        Response::redirect('/dashboard.php');
    }

    public function forgotPassword(): never
    {
        if (!Request::isPost()) {
            $this->render('auth/forgot', ['title' => 'Reset your password']);
        }
        $this->requirePost();
        $email = mb_strtolower(Request::input('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->back('/forgot-password.php', ['email' => 'Please enter a valid email address.']);
        }
        RateLimiter::enforce('reset:ip', Request::ip(), 5, 900);
        if (RateLimiter::hit('reset:email', $email, 3, 3600)) {
            AuthService::requestPasswordReset($email);
        }
        Session::flash('success', 'If an account exists for that address, a reset link has been sent. It expires in 60 minutes.');
        Response::redirect('/login.php');
    }

    public function resetPassword(): never
    {
        $token = Request::input('token');
        if (!Request::isPost()) {
            if (!AuthService::findResetToken($token)) {
                $this->render('auth/message', [
                    'title' => 'Link expired', 'heading' => 'This reset link is invalid or has expired',
                    'text' => 'Please request a new password reset link.', 'cta' => ['Request a new link', '/forgot-password.php'],
                ]);
            }
            $this->render('auth/reset', ['title' => 'Choose a new password', 'token' => $token]);
        }
        $this->requirePost();
        RateLimiter::enforce('reset-submit:ip', Request::ip(), 10, 900);
        $data = ['password' => Request::raw('password'), 'password_confirmation' => Request::raw('password_confirmation')];
        $v = Validator::make($data, ['password' => 'required|password', 'password_confirmation' => 'required|same:password']);
        if ($v->fails()) {
            $this->back('/reset-password.php?token=' . urlencode($token), $v->errors());
        }
        if (!AuthService::resetPassword($token, $data['password'])) {
            Session::flash('error', 'This reset link is invalid or has expired.');
            Response::redirect('/forgot-password.php');
        }
        Session::destroy();
        Session::start();
        Session::flash('success', 'Your password has been updated. Please sign in.');
        Response::redirect('/login.php');
    }
}
