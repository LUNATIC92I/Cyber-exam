<?php
declare(strict_types=1);

namespace App\Core;

/** File logger writing to /storage/logs (not web accessible). */
final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $line = sprintf("[%s] %s %s %s\n", gmdate('c'), $level, str_replace(["\r", "\n"], ' ', $message),
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : '');
        @file_put_contents(CEX_ROOT . '/storage/logs/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
