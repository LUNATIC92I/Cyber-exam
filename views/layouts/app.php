<?php
/** @var string $content */
use App\Core\Auth;
use App\Core\View;

$user = $user ?? Auth::user();
$pageTitle = isset($title) ? $title . ' · CYBER EXAM' : 'CYBER EXAM — Professional Cybersecurity Certification Exams';
$metaDescription = $description ?? 'CYBER EXAM delivers professional cybersecurity assessments: 100-question exams, automated scoring and verifiable digital certificates.';
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#04060b">
    <meta name="application-name" content="CYBER EXAM">
    <meta property="og:site_name" content="CYBER EXAM">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif; ?>
    <link rel="icon" href="<?= e(path('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(path('assets/fonts/inter-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(path('assets/fonts/space-grotesk-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php foreach (($scripts ?? []) as $script): ?>
    <script src="<?= e(asset($script)) ?>" defer></script>
    <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?= View::partial('header', ['user' => $user]) ?>
<?= View::partial('flash', ['flash' => $flash ?? []]) ?>
<main id="main">
<?= $content ?>
</main>
<?= View::partial('footer') ?>
</body>
</html>
