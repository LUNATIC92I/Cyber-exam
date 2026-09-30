<?php
/** @var string $content */
use App\Core\AdminAuth;
use App\Core\View;

$current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
$menu = [
    ['index.php', 'grid', 'Overview', 'dashboard.view'],
    ['users.php', 'users', 'Candidates', 'users.view'],
    ['exams.php', 'list', 'Exams & results', 'exams.view'],
    ['payments.php', 'card', 'Payments', 'payments.view'],
    ['certificates.php', 'award', 'Certificates', 'certificates.view'],
    ['questions.php', 'file', 'Questions', 'questions.view'],
    ['certifications.php', 'layers', 'Certifications', 'certifications.view'],
    ['settings.php', 'settings', 'Settings', 'settings.manage'],
];
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Admin') . ' · CYBER EXAM Admin') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?= e(path('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" data-admin-sidebar>
        <a class="brand" href="<?= e(path('admin/index.php')) ?>"><?= View::partial('logo') ?><span>CYBER EXAM</span></a>
        <span class="badge badge-blue admin-tag">BACK-OFFICE</span>
        <nav class="admin-nav" aria-label="Administration">
            <?php foreach ($menu as [$href, $ic, $label, $perm]): if (!AdminAuth::can($perm)) { continue; } ?>
                <a href="<?= e(path('admin/' . $href)) ?>" class="<?= $current === $href ? 'is-active' : '' ?>"><?= icon($ic) ?> <?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-me">
            <div class="avatar"><?= e(mb_strtoupper(mb_substr($admin['display_name'] ?? 'A', 0, 1))) ?></div>
            <div class="meta"><strong><?= e($admin['display_name'] ?? '') ?></strong><span><?= e(\App\Models\Admin::ROLES[$admin['role'] ?? ''] ?? '') ?></span></div>
            <form method="post" action="<?= e(path('admin/logout.php')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit" aria-label="Sign out"><?= icon('logout') ?></button>
            </form>
        </div>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-outline btn-sm admin-menu-btn" type="button" data-admin-menu aria-label="Menu"><?= icon('menu') ?></button>
            <h1><?= e($title ?? '') ?></h1>
            <span class="spacer"></span>
            <a class="btn btn-ghost btn-sm" href="<?= e(path('/')) ?>" target="_blank" rel="noopener"><?= icon('external') ?> View site</a>
        </header>
        <?= View::partial('flash', ['flash' => $flash ?? []]) ?>
        <main class="admin-content" id="main">
            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
