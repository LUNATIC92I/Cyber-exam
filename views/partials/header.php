<?php
use App\Core\View;

$current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: 'index.php');
$nav = [
    'certifications.php' => 'Certifications',
    'index.php#how-it-works' => 'How it works',
    'verify.php' => 'Verify a certificate',
];
?>
<header class="site-header" data-header>
    <div class="container nav">
        <a class="brand" href="<?= e(path('/')) ?>" aria-label="CYBER EXAM home">
            <?= View::partial('logo') ?>
            <span>CYBER EXAM</span>
        </a>
        <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="nav-menu" data-nav-toggle>
            <?= icon('menu') ?>
        </button>
        <div class="nav-menu" id="nav-menu" data-nav-menu>
            <ul class="nav-links">
                <?php foreach ($nav as $href => $label): ?>
                    <li><a href="<?= e(path($href)) ?>" class="<?= str_starts_with($href, $current) && $current !== 'index.php' ? 'is-active' : '' ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="nav-actions">
                <?php if (!empty($user)): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(path('dashboard.php')) ?>"><?= icon('grid', 'icon') ?> Dashboard</a>
                    <span class="user-chip" title="<?= e($user['email']) ?>">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1))) ?></span>
                        <?= e($user['first_name']) ?>
                    </span>
                    <form class="inline-form" method="post" action="<?= e(path('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit"><?= icon('logout') ?> Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(path('login.php')) ?>">Sign in</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(path('register.php')) ?>">Get started <?= icon('arrow-right', 'icon icon-arrow') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
