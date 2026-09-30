<section class="container">
    <div class="card status-card fade-up">
        <div class="status-icon is-error"><?= icon('alert') ?></div>
        <h1 class="h2"><?= e($heading) ?></h1>
        <p class="text-muted"><?= e($text) ?></p>
        <?php if (!empty($cta)): ?>
            <a class="btn btn-primary" href="<?= e(path($cta[1])) ?>"><?= e($cta[0]) ?></a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(path('login.php')) ?>">Go to sign in</a>
        <?php endif; ?>
    </div>
</section>
