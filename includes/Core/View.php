<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP template renderer. Templates live in /views and must escape all
 * dynamic output with e(). A template may be wrapped in a layout which receives
 * the rendered page as $content.
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::renderFile($layout, $data + ['content' => $content]);
    }

    public static function renderFile(string $template, array $data): string
    {
        $file = CEX_ROOT . '/views/' . str_replace(['..', "\0"], '', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Render a partial from inside a template. */
    public static function partial(string $template, array $data = []): string
    {
        return self::renderFile('partials/' . $template, $data);
    }

    public static function show(string $template, array $data = [], string $layout = 'layouts/app'): never
    {
        if (defined(Response::TEST_MODE_CONST)) {
            throw new HttpResponseException(['view' => $template, 'data' => array_keys($data)]);
        }
        echo self::render($template, $data, $layout);
        exit;
    }
}
