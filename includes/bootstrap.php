<?php
declare(strict_types=1);

/**
 * CYBER EXAM — application bootstrap.
 * Every public entry point requires this file first.
 */

use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;

if (defined('CEX_ROOT')) {
    return;
}
define('CEX_ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('CYBER EXAM requires PHP 8.2 or newer.');
}

// PSR-4 style autoloader for the App\ namespace (no Composer required).
spl_autoload_register(static function (string $class): void {
    static $map = [
        'App\\Core\\'        => '/includes/Core/',
        'App\\Controllers\\' => '/controllers/',
        'App\\Models\\'      => '/models/',
        'App\\Services\\'    => '/services/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = CEX_ROOT . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

// Optional Composer packages (not required by the core application).
if (is_file(CEX_ROOT . '/vendor/autoload.php')) {
    require CEX_ROOT . '/vendor/autoload.php';
}

require __DIR__ . '/helpers.php';

Env::load(defined('CEX_ENV_FILE') ? CEX_ENV_FILE : CEX_ROOT . '/.env');
Config::load(require CEX_ROOT . '/config/app.php');

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

$debug = (bool) Config::get('app.debug');
error_reporting(E_ALL);
ini_set('display_errors', $debug && PHP_SAPI !== 'cli' ? '1' : (PHP_SAPI === 'cli' ? '1' : '0'));
ini_set('log_errors', '1');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e) use ($debug): void {
        Logger::error(get_class($e) . ': ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
        if (!headers_sent()) {
            http_response_code(500);
        }
        if ($debug) {
            echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }
        try {
            Response::abort(500, 'An unexpected error occurred. Our team has been notified.');
        } catch (Throwable) {
            echo 'An unexpected error occurred.';
        }
    });

    Security::enforceHttps();
    Security::sendHeaders();
    Session::start();
}
