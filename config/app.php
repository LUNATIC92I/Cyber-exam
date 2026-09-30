<?php
declare(strict_types=1);

use App\Core\Env;

/**
 * Application configuration. Every secret comes from the environment (.env),
 * never from this file. This directory must not be web-accessible.
 */
return [
    'app' => [
        'name'      => 'CYBER EXAM',
        'env'       => Env::get('APP_ENV', 'production'),
        'debug'     => Env::bool('APP_DEBUG', false),
        'url'       => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'key'       => (string) Env::get('APP_KEY', ''),
        'timezone'  => Env::get('APP_TIMEZONE', 'UTC'),
        'force_https' => Env::bool('FORCE_HTTPS', false),
        'trusted_proxies' => array_filter(array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', '')))),
    ],

    'db' => [
        'host'     => Env::get('DB_HOST', '127.0.0.1'),
        'port'     => Env::int('DB_PORT', 3306),
        'database' => Env::get('DB_DATABASE', 'cyber_exam'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'socket'   => Env::get('DB_SOCKET', ''),
    ],

    'session' => [
        'name'            => 'cex_session',
        'idle_timeout'    => Env::int('SESSION_IDLE_MINUTES', 180) * 60,
        'absolute_timeout'=> 12 * 3600,
    ],

    'payments' => [
        // Active provider: "stripe" in production. "sandbox" is a local simulator (disabled in production).
        'provider' => Env::get('PAYMENT_PROVIDER', 'stripe'),
        'stripe' => [
            'secret_key'     => (string) Env::get('STRIPE_SECRET_KEY', ''),
            'webhook_secret' => (string) Env::get('STRIPE_WEBHOOK_SECRET', ''),
            'api_base'       => 'https://api.stripe.com/v1',
        ],
        'sandbox' => [
            'secret' => (string) Env::get('SANDBOX_WEBHOOK_SECRET', ''),
        ],
    ],

    'mail' => [
        'driver'     => Env::get('MAIL_DRIVER', 'log'),      // log | mail | smtp
        'host'       => Env::get('MAIL_HOST', ''),
        'port'       => Env::int('MAIL_PORT', 587),
        'username'   => Env::get('MAIL_USERNAME', ''),
        'password'   => Env::get('MAIL_PASSWORD', ''),
        'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),  // tls | ssl | none
        'from'       => Env::get('MAIL_FROM_ADDRESS', 'no-reply@cyber-exam.example'),
        'from_name'  => 'CYBER EXAM',
    ],

    'security' => [
        'login_max_attempts'   => 5,     // per email + IP inside the window
        'login_window_seconds' => 900,
        'account_lock_threshold' => 10,  // consecutive failures before a temporary lock
        'account_lock_minutes'   => 15,
    ],
];
