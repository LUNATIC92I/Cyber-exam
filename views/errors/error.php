<section class="container error-page">
    <div class="error-code gradient-text fade-up"><?= (int) $status ?></div>
    <h1 class="fade-up" data-delay="80"><?= e($title) ?></h1>
    <p class="text-muted fade-up" data-delay="140"><?= e($message) ?></p>
    <div class="hero-ctas fade-up" data-delay="200">
        <a class="btn btn-primary" href="<?= e(path('/')) ?>"><?= icon('home') ?> Back to home</a>
        <a class="btn btn-outline" href="<?= e(path('dashboard.php')) ?>">Dashboard</a>
    </div>
</section>
