<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** Thrown instead of exit() while running the test-suite. */
    public const TEST_MODE_CONST = 'CEX_TESTING';

    public static function redirect(string $path, int $status = 303): never
    {
        $target = preg_match('#^https?://#i', $path) ? $path : url($path);
        self::finish(fn () => header('Location: ' . $target, true, $status), ['redirect' => $target, 'status' => $status]);
    }

    public static function json(array $data, int $status = 200): never
    {
        self::finish(function () use ($data, $status) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }, ['json' => $data, 'status' => $status]);
    }

    public static function abort(int $status, string $message = ''): never
    {
        $titles = [400 => 'Bad request', 403 => 'Access denied', 404 => 'Page not found',
                   405 => 'Method not allowed', 419 => 'Session expired', 429 => 'Too many requests',
                   500 => 'Something went wrong', 503 => 'Service unavailable'];
        self::finish(function () use ($status, $message, $titles) {
            http_response_code($status);
            if (Request::wantsJson()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => $message ?: ($titles[$status] ?? 'Error')]);
                return;
            }
            echo View::render('errors/error', [
                'title'   => $titles[$status] ?? 'Error',
                'status'  => $status,
                'message' => $message ?: 'The request could not be completed.',
            ], 'layouts/app');
        }, ['abort' => $status, 'message' => $message]);
    }

    private static function finish(callable $emit, array $testPayload): never
    {
        if (defined(self::TEST_MODE_CONST)) {
            throw new HttpResponseException($testPayload);
        }
        $emit();
        exit;
    }
}
