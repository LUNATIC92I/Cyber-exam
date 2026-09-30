<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Request;
use App\Core\Security;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\User;

/**
 * Certificate issuance, integrity signature, public verification and revocation.
 * A certificate is only ever considered valid if it exists in the database,
 * its HMAC signature matches its data and it has not been revoked.
 */
final class CertificateService
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I

    public static function generateCode(string $certCode, int $year): string
    {
        $suffix = '';
        for ($i = 0; $i < 8; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return sprintf('CEX-%s-%d-%s', strtoupper($certCode), $year, $suffix);
    }

    public static function sign(array $c): string
    {
        return Security::hmac(implode('|', [
            $c['certificate_code'], (int) $c['user_id'], (int) $c['attempt_id'], (int) $c['certification_id'],
            $c['candidate_name'], $c['certification_name'], number_format((float) $c['score_percent'], 2, '.', ''),
            $c['issued_at'],
        ]));
    }

    public static function hasValidSignature(array $c): bool
    {
        return hash_equals(self::sign($c), (string) $c['signature']);
    }

    /** Issues the certificate for a passed attempt (idempotent). */
    public static function issue(array $attempt): array
    {
        if ((int) ($attempt['passed'] ?? 0) !== 1) {
            throw new \LogicException('Certificates can only be issued for passed attempts.');
        }
        $existing = Certificate::findByAttempt((int) $attempt['id']);
        if ($existing) {
            return $existing;
        }
        $user = User::find((int) $attempt['user_id']);
        $cert = Certification::find((int) $attempt['certification_id']);
        $issuedAt = $attempt['submitted_at'] ?: Database::now();

        for ($try = 0; $try < 5; $try++) {
            $data = [
                'certificate_code'   => self::generateCode($cert['code'], (int) substr($issuedAt, 0, 4)),
                'user_id'            => (int) $user['id'],
                'attempt_id'         => (int) $attempt['id'],
                'certification_id'   => (int) $cert['id'],
                'candidate_name'     => User::fullName($user),
                'certification_name' => $cert['name'],
                'score_percent'      => number_format((float) $attempt['score_percent'], 2, '.', ''),
                'issued_at'          => $issuedAt,
            ];
            $data['signature'] = self::sign($data);
            try {
                $id = Database::insert(
                    'INSERT INTO certificates (certificate_code, user_id, attempt_id, certification_id, candidate_name,
                        certification_name, score_percent, issued_at, status, signature)
                     VALUES (:certificate_code, :user_id, :attempt_id, :certification_id, :candidate_name,
                        :certification_name, :score_percent, :issued_at, \'valid\', :signature)',
                    $data
                );
                Audit::log('certificate.issued', 'certificate', $data['certificate_code'], [], 'system', null);
                return Database::fetch('SELECT * FROM certificates WHERE id = :id', ['id' => $id]);
            } catch (\PDOException $e) {
                // 23000 = duplicate key: retry with a new random code; otherwise rethrow.
                if ($e->getCode() !== '23000' || Certificate::findByAttempt((int) $attempt['id'])) {
                    $again = Certificate::findByAttempt((int) $attempt['id']);
                    if ($again) {
                        return $again;
                    }
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Could not allocate a unique certificate code.');
    }

    /**
     * Public verification. Returns ['status' => verified|revoked|not_found|invalid, 'certificate' => ?array]
     * and records the lookup.
     */
    public static function verify(string $input, bool $log = true): array
    {
        $code = Certificate::normaliseCode($input);
        $certificate = null;
        if (!Certificate::isValidFormat($code)) {
            $status = 'invalid';
        } else {
            $certificate = Certificate::findByCode($code);
            if (!$certificate) {
                $status = 'not_found';
            } elseif (!self::hasValidSignature($certificate)) {
                $status = 'invalid';        // data altered outside the application
                $certificate = null;
            } else {
                $status = $certificate['status'] === 'revoked' ? 'revoked' : 'verified';
            }
        }
        if ($log) {
            Database::query(
                'INSERT INTO certificate_verifications (certificate_id, queried_code, result, ip_hash, user_agent)
                 VALUES (:c, :q, :r, :ip, :ua)',
                [
                    'c' => $certificate['id'] ?? null, 'q' => mb_substr($code, 0, 64), 'r' => $status,
                    'ip' => Security::hmac(PHP_SAPI === 'cli' ? 'cli' : Request::ip()),
                    'ua' => PHP_SAPI === 'cli' ? 'cli' : Request::userAgent(),
                ]
            );
        }
        return ['status' => $status, 'certificate' => $certificate, 'code' => $code];
    }

    public static function revoke(string $code, string $reason): bool
    {
        $c = Certificate::findByCode($code);
        if (!$c || $c['status'] === 'revoked') {
            return false;
        }
        Database::query('UPDATE certificates SET status = \'revoked\', revoked_at = :now, revoked_reason = :r WHERE id = :id',
            ['now' => Database::now(), 'r' => mb_substr($reason, 0, 255), 'id' => $c['id']]);
        Audit::log('certificate.revoked', 'certificate', $code, ['reason' => $reason]);
        return true;
    }

    public static function reinstate(string $code): bool
    {
        $c = Certificate::findByCode($code);
        if (!$c || $c['status'] !== 'revoked') {
            return false;
        }
        Database::query('UPDATE certificates SET status = \'valid\', revoked_at = NULL, revoked_reason = NULL WHERE id = :id', ['id' => $c['id']]);
        Audit::log('certificate.reinstated', 'certificate', $code);
        return true;
    }

    public static function verifyUrl(string $code): string
    {
        return url('verify.php', ['id' => $code]);
    }

    /** Path of the cached PDF (outside public reach, see /certificates/.htaccess). */
    public static function pdfPath(array $certificate): string
    {
        return CEX_ROOT . '/certificates/' . $certificate['certificate_code'] . '-' . substr(hash('sha256', $certificate['signature'] . $certificate['status']), 0, 12) . '.pdf';
    }

    public static function pdf(array $certificate): string
    {
        $path = self::pdfPath($certificate);
        if (is_file($path)) {
            return (string) file_get_contents($path);
        }
        $bytes = (new CertificatePdf())->render($certificate);
        @file_put_contents($path, $bytes, LOCK_EX);
        return $bytes;
    }
}
