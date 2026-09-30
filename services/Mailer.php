<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\View;

/**
 * Transactional email. Drivers:
 *  - log  : writes .eml files to /storage/mail (development)
 *  - mail : PHP mail()
 *  - smtp : built-in SMTP client (STARTTLS / SSL, AUTH LOGIN)
 */
final class Mailer
{
    /** @var array<int,array> Messages sent during this request (used by tests). */
    public static array $sent = [];

    public static function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        $data['subject'] = $subject;
        $html = View::render('emails/' . $template, $data, 'emails/layout');
        $text = trim(html_entity_decode(strip_tags(preg_replace(['#<br\s*/?>#i', '#</(p|h1|h2|tr|div)>#i'], ["\n", "\n\n"], $html) ?? ''), ENT_QUOTES, 'UTF-8'));
        $text = preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? '') ?? '';
        return self::send($to, $subject, $html, $text);
    }

    public static function send(string $to, string $subject, string $html, string $text): bool
    {
        // Header-injection defence.
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $from = (string) Config::get('mail.from');
        $fromName = (string) Config::get('mail.from_name', 'CYBER EXAM');
        $boundary = 'cex_' . bin2hex(random_bytes(12));
        $host = parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost';

        $headers = [
            'Date' => date(DATE_RFC2822),
            'From' => sprintf('%s <%s>', self::encodeHeader($fromName), $from),
            'To' => $to,
            'Subject' => self::encodeHeader($subject),
            'Message-ID' => sprintf('<%s@%s>', bin2hex(random_bytes(16)), $host),
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer' => 'CYBER EXAM',
        ];
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--{$boundary}--\r\n";

        self::$sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html, 'text' => $text];

        try {
            return match ((string) Config::get('mail.driver', 'log')) {
                'smtp' => self::smtp($from, $to, $headers, $body),
                'mail' => mail($to, $headers['Subject'], $body, self::headerString(array_diff_key($headers, ['To' => 1, 'Subject' => 1])), '-f' . $from),
                default => self::log($headers, $body, $text),
            };
        } catch (\Throwable $e) {
            Logger::error('Mail delivery failed: ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
            return false;
        }
    }

    private static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    private static function headerString(array $headers): string
    {
        $out = '';
        foreach ($headers as $k => $v) {
            $out .= $k . ': ' . $v . "\r\n";
        }
        return $out;
    }

    private static function log(array $headers, string $body, string $text): bool
    {
        if (PHP_SAPI === 'cli' && defined('CEX_TESTING')) {
            return true;
        }
        $dir = CEX_ROOT . '/storage/mail';
        $name = gmdate('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        file_put_contents("{$dir}/{$name}.eml", self::headerString($headers) . "\r\n" . $body, LOCK_EX);
        file_put_contents("{$dir}/{$name}.txt", 'To: ' . $headers['To'] . "\nSubject: " . $headers['Subject'] . "\n\n" . $text, LOCK_EX);
        return true;
    }

    private static function smtp(string $from, string $to, array $headers, string $body): bool
    {
        $c = Config::get('mail');
        $enc = strtolower((string) $c['encryption']);
        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: {$errstr}");
        }
        stream_set_timeout($fp, 15);
        $expect = function (array $codes) use ($fp): string {
            $resp = '';
            while (($line = fgets($fp, 515)) !== false) {
                $resp .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            if (!in_array((int) substr($resp, 0, 3), $codes, true)) {
                throw new \RuntimeException('SMTP error: ' . trim($resp));
            }
            return $resp;
        };
        $cmd = function (string $command, array $codes) use ($fp, $expect): string {
            fwrite($fp, $command . "\r\n");
            return $expect($codes);
        };
        $local = parse_url((string) Config::get('app.url'), PHP_URL_HOST) ?: 'localhost';
        $expect([220]);
        $cmd("EHLO {$local}", [250]);
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('SMTP STARTTLS failed');
            }
            $cmd("EHLO {$local}", [250]);
        }
        if ($c['username'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode((string) $c['username']), [334]);
            $cmd(base64_encode((string) $c['password']), [235]);
        }
        $cmd("MAIL FROM:<{$from}>", [250]);
        $cmd("RCPT TO:<{$to}>", [250, 251]);
        $cmd('DATA', [354]);
        $data = self::headerString($headers) . "\r\n" . $body;
        $data = preg_replace('/^\./m', '..', $data);
        $cmd($data . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }
}
