<?php
/** @var string $content */
use App\Core\View;
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
</head>
<body class="admin-body">
<?= View::partial('flash', ['flash' => $flash ?? []]) ?>
<main class="admin-auth" id="main">
    <div class="auth-card card fade-up">
        <div class="brand"><?= View::partial('logo') ?><span>CYBER EXAM</span></div>
        <?= $content ?>
    </div>
</main>
</body>
</html>
