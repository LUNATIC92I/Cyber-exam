<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Key/value platform settings editable from the back-office. */
final class Setting
{
    private static ?array $cache = null;

    /** Allowed keys with their validation rule, so admins cannot inject arbitrary settings. */
    public const SCHEMA = [
        'default_duration_minutes'  => 'required|between:10,600',
        'default_price_cents'       => 'required|between:0,1000000',
        'default_currency'          => 'required|in:EUR,USD,GBP,XAF,XOF',
        'passing_score'             => 'required|between:1,100',
        'questions_per_exam'        => 'required|between:10,200',
        'require_email_verification'=> 'required|in:0,1',
        'registration_open'         => 'required|in:0,1',
        'support_email'             => 'required|email|max:191',
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::fetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, (string) $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return self::get($key, $default ? '1' : '0') === '1';
    }

    public static function set(string $key, string $value): void
    {
        Database::query(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['k' => $key, 'v' => $value]
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
