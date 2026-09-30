<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AdminAuth;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Models\Certificate;
use App\Services\CertificateService;
use App\Services\QrCode;

final class CertificateController extends Controller
{
    /** /verify.php — public verification form and result. */
    public function verify(): never
    {
        $input = Request::input('id');
        $result = null;
        if ($input !== '') {
            RateLimiter::enforce('verify:ip', Request::ip(), 60, 600);
            $result = CertificateService::verify($input);
        }
        $this->render('pages/verify', [
            'title' => 'Verify a certificate',
            'query' => $input,
            'result' => $result,
        ]);
    }

    /** /certificate.php?id=CODE — public certificate page (data always read from the DB). */
    public function show(): never
    {
        $input = Request::query('id');
        RateLimiter::enforce('verify:ip', Request::ip(), 60, 600);
        $result = CertificateService::verify($input);
        if (!in_array($result['status'], ['verified', 'revoked'], true)) {
            Response::abort(404, 'No certificate matches this ID. Check the ID or use the verification page.');
        }
        $c = $result['certificate'];
        $isOwner = Auth::id() !== null && Auth::id() === (int) $c['user_id'];
        $this->render('pages/certificate', [
            'title' => 'Certificate ' . $c['certificate_code'],
            'certificate' => $c,
            'status' => $result['status'],
            'qrSvg' => (new QrCode(CertificateService::verifyUrl($c['certificate_code'])))->toSvg(2),
            'canDownload' => $isOwner || AdminAuth::can('certificates.view'),
        ]);
    }

    /** /certificate-download.php?id=CODE — PDF for the owner or back-office staff only. */
    public function download(): never
    {
        $code = Certificate::normaliseCode(Request::query('id'));
        $c = Certificate::isValidFormat($code) ? Certificate::findByCode($code) : null;
        $isOwner = $c && Auth::id() !== null && Auth::id() === (int) $c['user_id'];
        if (!$c || !($isOwner || AdminAuth::can('certificates.view'))) {
            if (!Auth::check() && !AdminAuth::admin()) {
                Auth::require();
            }
            Response::abort(404, 'Certificate not found.');
        }
        if (!CertificateService::hasValidSignature($c)) {
            Response::abort(409, 'This certificate failed its integrity check. Please contact support.');
        }
        $bytes = CertificateService::pdf($c);
        Audit::log('certificate.downloaded', 'certificate', $c['certificate_code']);
        if (defined('CEX_TESTING')) {
            throw new \App\Core\HttpResponseException(['pdf' => strlen($bytes)]);
        }
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($bytes));
        header('Content-Disposition: ' . (Request::query('inline') === '1' ? 'inline' : 'attachment') . '; filename="' . $c['certificate_code'] . '.pdf"');
        header('Cache-Control: private, no-store');
        echo $bytes;
        exit;
    }
}
