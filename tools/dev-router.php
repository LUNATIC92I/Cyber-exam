<?php
declare(strict_types=1);

/**
 * Router for PHP's built-in web server (development only):
 *   php -S localhost:8000 tools/dev-router.php
 * Mirrors the .htaccess rules: blocks private directories and dotfiles.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$root = dirname(__DIR__);

$blocked = '#^/(config|controllers|models|views|includes|services|storage|database|tools|tests|vendor|certificates|uploads)(/|$)|/\.|\.(sql|md|log|env|ini|lock|sh)$|^/seed\.php$#i';
if (preg_match($blocked, $path)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}
if ($path === '/' || $path === '') {
    $path = '/index.php';
}
$file = realpath($root . $path);
if ($file === false || !str_starts_with($file, $root)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}
if (is_dir($file)) {
    $file = rtrim($file, '/') . '/index.php';
    if (!is_file($file)) {
        http_response_code(404);
        return true;
    }
}
if (str_ends_with($file, '.php')) {
    $_SERVER['SCRIPT_NAME'] = substr($file, strlen($root));
    $_SERVER['SCRIPT_FILENAME'] = $file;
    chdir(dirname($file));
    require $file;
    return true;
}
return false; // static file served by the built-in server
