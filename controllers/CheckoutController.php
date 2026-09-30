<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Core\Uuid;
use App\Models\Certification;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SandboxGateway;
use App\Services\Payment\WebhookException;

/**
 * Checkout flow. IMPORTANT: returning from the provider NEVER marks a payment
 * as paid; the status page only reads the state written by the webhook.
 */
final class CheckoutController extends Controller
{
    public function handle(): never
    {
        $user = Auth::require();
        Security::noStore();

        if (Request::isPost()) {
            $action = Request::input('action');
            if ($action === 'sandbox') {
                $this->sandboxComplete($user);
            }
            $this->create($user);
        }
        if (($uuid = Request::query('sandbox')) !== '') {
            $this->sandboxPage($user, $uuid);
        }
        if (($uuid = Request::query('payment')) !== '') {
            $this->statusPage($user, $uuid);
        }
        $slug = Request::query('certification');
        $cert = $slug !== '' ? Certification::findBySlug($slug) : null;
        if (!$cert || !$cert['is_active']) {
            Response::redirect('/certifications.php');
        }
        $this->render('pages/checkout', [
            'title' => 'Checkout — ' . $cert['name'],
            'user' => $user,
            'certification' => $cert,
            'credit' => Payment::availableCredit((int) $user['id'], (int) $cert['id']),
            'active' => ExamAttempt::activeFor((int) $user['id'], (int) $cert['id']),
            'needsVerification' => Setting::bool('require_email_verification', true) && !$user['email_verified_at'],
            'provider' => (string) config('payments.provider'),
        ]);
    }

    private function create(array $user): never
    {
        $this->requirePost();
        $cert = Certification::find(Request::int('certification_id'));
        if (!$cert || !$cert['is_active']) {
            Response::abort(404, 'Certification not found.');
        }
        if (Setting::bool('require_email_verification', true) && !$user['email_verified_at']) {
            Session::flash('error', 'Please confirm your email address before purchasing an exam.');
            Response::redirect('/checkout.php?certification=' . urlencode($cert['slug']));
        }
        if (Payment::availableCredit((int) $user['id'], (int) $cert['id'])) {
            Session::flash('info', 'You already have a paid exam for this certification. You can start it now.');
            Response::redirect('/dashboard.php');
        }
        RateLimiter::enforce('checkout:user', (string) $user['id'], 10, 3600);
        try {
            $redirect = PaymentService::startCheckout($user, $cert);
        } catch (\Throwable $e) {
            Logger::error('Checkout error: ' . $e->getMessage());
            Session::flash('error', 'The payment service is temporarily unavailable. Please try again in a few minutes.');
            Response::redirect('/checkout.php?certification=' . urlencode($cert['slug']));
        }
        Response::redirect($redirect);
    }

    private function ownedPayment(array $user, string $uuid): array
    {
        $payment = Uuid::isValid($uuid) ? Payment::findByUuid($uuid) : null;
        if (!$payment || (int) $payment['user_id'] !== (int) $user['id']) {
            Response::abort(404, 'Payment not found.');
        }
        return $payment;
    }

    /** Return page after the provider redirect: shows the webhook-driven status. */
    private function statusPage(array $user, string $uuid): never
    {
        $payment = $this->ownedPayment($user, $uuid);
        $cert = Certification::find((int) $payment['certification_id']);
        $this->render('pages/payment-status', [
            'title' => 'Payment status',
            'payment' => $payment,
            'certification' => $cert,
            'returned' => Request::query('status'),
        ]);
    }

    /** JSON endpoint polled by the status page. */
    public function statusApi(): never
    {
        $user = Auth::require();
        $payment = $this->ownedPayment($user, Request::query('payment'));
        Response::json(['status' => $payment['status'], 'paid_at' => $payment['paid_at']]);
    }

    // ------------------------------------------------------- local sandbox gateway

    private function sandboxPage(array $user, string $uuid): never
    {
        if (SandboxGateway::disabled()) {
            Response::abort(404);
        }
        $payment = $this->ownedPayment($user, $uuid);
        $this->render('pages/sandbox-checkout', [
            'title' => 'Sandbox payment',
            'payment' => $payment,
            'certification' => Certification::find((int) $payment['certification_id']),
        ], 'layouts/app');
    }

    /** Simulates the provider: emits a SIGNED webhook through the normal pipeline. */
    private function sandboxComplete(array $user): never
    {
        $this->requirePost();
        if (SandboxGateway::disabled()) {
            Response::abort(404);
        }
        $payment = $this->ownedPayment($user, Request::input('payment'));
        $outcome = Request::input('outcome') === 'fail' ? 'fail' : 'pay';
        $event = $outcome === 'pay'
            ? SandboxGateway::buildEvent($payment, 'checkout.session.completed', 'paid')
            : SandboxGateway::buildEvent($payment, 'checkout.session.expired', 'unpaid');
        try {
            PaymentService::handleWebhook('sandbox', $event['payload'], $event['headers']);
        } catch (WebhookException $e) {
            Logger::error('Sandbox webhook rejected: ' . $e->getMessage());
        }
        Response::redirect('/checkout.php?payment=' . $payment['uuid'] . '&status=' . ($outcome === 'pay' ? 'success' : 'cancelled'));
    }
}
