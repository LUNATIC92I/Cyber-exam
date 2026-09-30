<?php $c = $certification; ?>
<section class="page-head">
    <div class="container">
        <a class="btn btn-ghost btn-sm" href="<?= e(path('dashboard.php')) ?>"><?= icon('arrow-left') ?> Back to dashboard</a>
        <h1 class="fade-up">Checkout</h1>
        <p class="fade-up" data-delay="60">One payment unlocks one attempt at the <?= e($c['name']) ?> certification exam.</p>
    </div>
</section>
<div class="container">
    <div class="checkout">
        <div class="card fade-up accent-<?= e($c['accent']) ?>">
            <div class="card-header">
                <div class="cert-icon"><?= icon(cert_icon($c['code'])) ?></div>
                <div>
                    <h2><?= e($c['name']) ?> Certification Exam</h2>
                    <span class="text-muted"><?= e($c['tagline']) ?></span>
                </div>
            </div>
            <h3>Exam rules</h3>
            <ul class="rules">
                <li><?= icon('list') ?> <?= (int) $c['question_count'] ?> multiple-choice questions, 1 point each, one correct answer per question.</li>
                <li><?= icon('clock') ?> <?= (int) $c['duration_minutes'] ?> minutes. The timer runs on our servers and continues if you close the page.</li>
                <li><?= icon('zap') ?> When time runs out, your exam is submitted automatically.</li>
                <li><?= icon('target') ?> A score of <?= (int) $c['passing_score'] ?>% or more is required to pass and receive a certificate.</li>
                <li><?= icon('refresh') ?> Questions and answer options are randomised for each attempt.</li>
                <li><?= icon('user') ?> The exam must be completed individually without external assistance.</li>
            </ul>
        </div>
        <aside class="card fade-up" data-delay="100">
            <h3>Order summary</h3>
            <div class="summary-row"><span>Certification</span><strong><?= e($c['name']) ?></strong></div>
            <div class="summary-row"><span>Attempts included</span><span>1</span></div>
            <div class="summary-row"><span>Candidate</span><span><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span></div>
            <div class="summary-total"><span>Total</span><strong><?= e(money((int) $c['price_cents'], $c['currency'])) ?></strong></div>
            <div class="divider"></div>
            <?php if ($active): ?>
                <a class="btn btn-primary btn-lg btn-block" href="<?= e(path('exam.php', ['attempt' => $active['uuid']])) ?>">Resume your exam <?= icon('arrow-right', 'icon icon-arrow') ?></a>
            <?php elseif ($credit): ?>
                <p class="text-muted">Your payment is confirmed. You can start the exam now.</p>
                <form method="post" action="<?= e(path('exam.php')) ?>" data-confirm-start>
                    <?= csrf_field() ?>
                    <input type="hidden" name="certification_id" value="<?= (int) $c['id'] ?>">
                    <button class="btn btn-primary btn-lg btn-block" type="submit">START CERTIFICATION <?= icon('arrow-right', 'icon icon-arrow') ?></button>
                </form>
            <?php elseif ($needsVerification): ?>
                <div class="banner"><?= icon('mail') ?> Confirm your email address to continue.</div>
                <form method="post" action="<?= e(path('resend-verification.php')) ?>" class="stack">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-block" type="submit">Resend confirmation email</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(path('checkout.php')) ?>" data-loading>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="certification_id" value="<?= (int) $c['id'] ?>">
                    <button class="btn btn-primary btn-lg btn-block" type="submit">PAY <?= e(money((int) $c['price_cents'], $c['currency'])) ?> <?= icon('arrow-right', 'icon icon-arrow') ?></button>
                </form>
                <div class="secure-note"><?= icon('key', 'icon icon-sm') ?> Secure payment<?= $provider === 'stripe' ? ' processed by Stripe' : '' ?>. Card details never touch our servers.</div>
            <?php endif; ?>
        </aside>
    </div>
</div>
