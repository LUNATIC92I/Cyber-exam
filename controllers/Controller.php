<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/** Base controller with small helpers shared by all controllers. */
abstract class Controller
{
    protected function render(string $template, array $data = [], string $layout = 'layouts/app'): never
    {
        $data += [
            'flash' => Session::pullFlash(),
            'old' => Session::pullOld(),
            'errors' => Session::pullErrors(),
        ];
        View::show($template, $data, $layout);
    }

    protected function requirePost(): void
    {
        if (!Request::isPost()) {
            Response::abort(405, 'Method not allowed.');
        }
        Csrf::verifyRequest();
    }

    /** Redirects back to a form with validation errors and the (non-secret) input. */
    protected function back(string $path, array $errors, array $input = []): never
    {
        Session::flashErrors($errors);
        Session::flashInput($input ?: $_POST);
        Response::redirect($path);
    }

    /** Only allow local redirect targets (open-redirect protection). */
    protected function safeRedirectTarget(?string $target, string $fallback): string
    {
        if (!is_string($target) || $target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, '\\')) {
            return $fallback;
        }
        $basePath = rtrim((string) (parse_url((string) config('app.url'), PHP_URL_PATH) ?? ''), '/');
        if ($basePath !== '' && str_starts_with($target, $basePath . '/')) {
            $target = substr($target, strlen($basePath));
        }
        return $target;
    }
}
