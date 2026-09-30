<?php
/**
 * @var array $c        certification
 * @var ?array $access  ['state' => pay|start|resume, 'attempt' => uuid]
 * @var bool $loggedIn
 */
$state = $access['state'] ?? null;
$delay = (int) ($delay ?? 0);
?>
<article class="cert-card accent-<?= e($c['accent']) ?> reveal" data-delay="<?= $delay ?>" data-tilt>
    <div class="cert-top">
        <div class="cert-icon"><?= icon(cert_icon($c['code'])) ?></div>
        <span class="cert-code"><?= e($c['code']) ?></span>
    </div>
    <h3><?= e($c['name']) ?></h3>
    <p><?= e($c['tagline']) ?></p>
    <ul class="cert-specs">
        <li><?= icon('list') ?><span><strong><?= (int) $c['question_count'] ?></strong> Questions</span></li>
        <li><?= icon('clock') ?><span><strong><?= (int) $c['duration_minutes'] ?></strong> Minutes</span></li>
        <li><?= icon('target') ?><span>Passing <strong><?= (int) $c['passing_score'] ?>%</strong></span></li>
        <li><?= icon('euro') ?><span>Price <strong><?= e(money((int) $c['price_cents'], $c['currency'])) ?></strong></span></li>
    </ul>
    <?php if (!empty($c['topics'])): ?>
        <div class="cert-topics" aria-label="Topics covered">
            <?php foreach (array_slice($c['topics'], 0, 10) as $t): ?><span><?= e($t) ?></span><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="cert-actions">
        <?php if ($state === 'resume'): ?>
            <a class="btn btn-primary" href="<?= e(path('exam.php', ['attempt' => $access['attempt']])) ?>">RESUME EXAM <?= icon('arrow-right', 'icon icon-arrow') ?></a>
        <?php elseif ($state === 'start'): ?>
            <form method="post" action="<?= e(path('exam.php')) ?>" class="btn-block" data-confirm-start>
                <?= csrf_field() ?>
                <input type="hidden" name="certification_id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-primary btn-block" type="submit">START CERTIFICATION <?= icon('arrow-right', 'icon icon-arrow') ?></button>
            </form>
        <?php elseif ($state === 'pay'): ?>
            <a class="btn btn-primary" href="<?= e(path('checkout.php', ['certification' => $c['slug']])) ?>">PAY <?= e(money((int) $c['price_cents'], $c['currency'])) ?> <?= icon('arrow-right', 'icon icon-arrow') ?></a>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(path('register.php')) ?>">START CERTIFICATION <?= icon('arrow-right', 'icon icon-arrow') ?></a>
        <?php endif; ?>
    </div>
</article>
