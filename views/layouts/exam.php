<?php
/** @var string $content */
use App\Core\View;
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e(($title ?? 'Exam') . ' · CYBER EXAM') ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#04060b">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= e(path('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(path('assets/fonts/inter-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <script src="<?= e(asset('js/exam.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<header class="exam-header">
    <div class="container">
        <span class="brand" aria-label="CYBER EXAM"><?= View::partial('logo') ?></span>
        <div class="exam-title">
            <strong><?= e($certification['name']) ?> Certification Exam</strong>
            <span>Answers are saved automatically</span>
        </div>
        <div class="timer" role="timer" aria-live="off" aria-label="Time remaining" data-timer><?= icon('clock') ?><span data-timer-text>--:--:--</span></div>
        <button class="btn btn-outline btn-sm palette-toggle" type="button" data-palette-toggle aria-controls="exam-side" aria-expanded="false"><?= icon('grid') ?><span class="sr-only">Questions</span></button>
    </div>
    <div class="exam-progress" aria-hidden="true"><div class="exam-progress-bar" data-progress></div></div>
</header>
<?= View::partial('flash', ['flash' => $flash ?? []]) ?>
<main id="main">
<?= $content ?>
</main>
</body>
</html>
