<?php
$status = $payment['status'];
?>
<section class="container">
    <div class="card status-card fade-up" data-payment-status="<?= e($payment['uuid']) ?>" data-status="<?= e($status) ?>" data-endpoint="<?= e(path('api/payment-status.php')) ?>">
        <div data-state="pending" <?= $status === 'pending' ? '' : 'hidden' ?>>
            <div class="status-icon pulse"><?= icon('clock') ?></div>
            <h1 class="h2">Confirming your payment…</h1>
            <p class="text-muted">
                <?= $returned === 'cancelled' ? 'The checkout was cancelled. If you completed the payment, it will appear here once confirmed.' : 'We are waiting for the secure confirmation from our payment provider. This usually takes a few seconds.' ?>
            </p>
            <a class="btn btn-outline" href="<?= e(path('checkout.php', ['certification' => $certification['slug']])) ?>">Back to checkout</a>
        </div>
        <div data-state="paid" <?= $status === 'paid' ? '' : 'hidden' ?>>
            <div class="status-icon is-success"><?= icon('check') ?></div>
            <h1 class="h2">Payment Confirmed</h1>
            <p class="text-muted">Your <?= e($certification['name']) ?> exam is unlocked. A confirmation email is on its way.</p>
            <form method="post" action="<?= e(path('exam.php')) ?>" data-confirm-start>
                <?= csrf_field() ?>
                <input type="hidden" name="certification_id" value="<?= (int) $certification['id'] ?>">
                <button class="btn btn-primary btn-lg" type="submit">START CERTIFICATION <?= icon('arrow-right', 'icon icon-arrow') ?></button>
            </form>
            <p><a href="<?= e(path('dashboard.php')) ?>">Start later from my dashboard</a></p>
        </div>
        <div data-state="failed" <?= in_array($status, ['failed', 'refunded'], true) ? '' : 'hidden' ?>>
            <div class="status-icon is-error"><?= icon('x') ?></div>
            <h1 class="h2">Payment not completed</h1>
            <p class="text-muted">No charge was confirmed for this order. You can try again at any time.</p>
            <a class="btn btn-primary" href="<?= e(path('checkout.php', ['certification' => $certification['slug']])) ?>">Try again</a>
        </div>
    </div>
</section>
