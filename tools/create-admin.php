<?php
declare(strict_types=1);

/**
 * Create a back-office account (CLI only).
 *   php tools/create-admin.php "Display Name" admin@example.com [super_admin|admin|support]
 * The password is read interactively (not echoed, never passed on the command line).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/bootstrap.php';

use App\Core\Validator;
use App\Models\Admin;

[$name, $email, $role] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? 'super_admin'];
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(Admin::ROLES[$role])) {
    fwrite(STDERR, "Usage: php tools/create-admin.php \"Display Name\" email@example.com [super_admin|admin|support]\n");
    exit(1);
}
if (Admin::findByEmail($email)) {
    fwrite(STDERR, "An administrator with this email already exists.\n");
    exit(1);
}
$password = getenv('CEX_ADMIN_PASSWORD') ?: '';
if ($password === '') {
    echo 'Password: ';
    system('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo PHP_EOL;
}
if (!Validator::strongPassword($password)) {
    fwrite(STDERR, "Password must be 10+ characters with upper, lower, digit and symbol.\n");
    exit(1);
}
$id = Admin::create($name, $email, $password, $role);
echo "Administrator #{$id} created with role {$role}.\n";
