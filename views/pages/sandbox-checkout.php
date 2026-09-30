<section class="container">
    <div class="card status-card fade-up">
        <span class="badge badge-warning">DEVELOPMENT SANDBOX</span>
        <h1 class="h2">Simulated payment provider</h1>
        <p class="text-muted">This page replaces the hosted Stripe checkout in local environments. Your choice sends a <strong>signed webhook</strong> through the same verification pipeline as real payments. It is disabled when <code>APP_ENV=production</code>.</p>
        <div class="summary-row"><span><?= e($certification['name']) ?></span><strong><?= e(money((int) $payment['amount'], $payment['currency'])) ?></strong></div>
        <div class="summary-row"><span>Reference</span><span class="mono"><?= e($payment['transaction_id'] ?? '') ?></span></div>
        <div class="divider"></div>
        <div class="row">
            <form method="post" action="<?= e(path('checkout.php')) ?>" class="spacer">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="sandbox">
                <input type="hidden" name="payment" value="<?= e($payment['uuid']) ?>">
                <input type="hidden" name="outcome" value="pay">
                <button class="btn btn-success btn-block" type="submit" data-testid="sandbox-pay"><?= icon('check') ?> Simulate successful payment</button>
            </form>
            <form method="post" action="<?= e(path('checkout.php')) ?>" class="spacer">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="sandbox">
                <input type="hidden" name="payment" value="<?= e($payment['uuid']) ?>">
                <input type="hidden" name="outcome" value="fail">
                <button class="btn btn-danger btn-block" type="submit"><?= icon('x') ?> Simulate failure</button>
            </form>
        </div>
    </div>
</section>
