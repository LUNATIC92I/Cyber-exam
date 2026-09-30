<?php
$passed = (bool) $attempt['passed'];
$score = (float) $attempt['score_percent'];
$correct = (int) $attempt['correct_count'];
$total = (int) $attempt['total_questions'];
$circ = 2 * M_PI * 104;
$thresholdAngle = (int) $attempt['passing_score'] / 100;
?>
<div class="container container-md">
    <section class="result-hero" data-result data-score="<?= e((string) $score) ?>" data-passed="<?= $passed ? '1' : '0' ?>">
        <div class="eyebrow fade-up">EXAM COMPLETED</div>
        <h1 class="fade-up h2" data-delay="60"><?= e($certification['name']) ?> Certification</h1>
        <div class="ring-wrap fade-up" data-delay="120">
            <svg class="ring" viewBox="0 0 240 240" aria-hidden="true">
                <defs>
                    <linearGradient id="ring-grad" x1="0" y1="0" x2="1" y2="1">
                        <?php if ($passed): ?><stop offset="0" stop-color="#2f6bff"/><stop offset="1" stop-color="#22d3ee"/>
                        <?php else: ?><stop offset="0" stop-color="#f43f5e"/><stop offset="1" stop-color="#fb923c"/><?php endif; ?>
                    </linearGradient>
                </defs>
                <circle class="ring-track" cx="120" cy="120" r="104"/>
                <circle class="ring-value" cx="120" cy="120" r="104" stroke-dasharray="<?= round($circ, 2) ?>" stroke-dashoffset="<?= round($circ, 2) ?>" data-ring data-circ="<?= round($circ, 2) ?>"/>
                <circle class="ring-threshold" cx="120" cy="120" r="104" stroke-dasharray="1.6 <?= round($circ, 2) ?>" stroke-dashoffset="<?= round(-$circ * $thresholdAngle, 2) ?>"/>
            </svg>
            <div class="ring-center">
                <div class="ring-score"><span data-score-text>0</span>%</div>
                <div class="ring-caption">Your score</div>
            </div>
        </div>
        <div class="verdict <?= $passed ? 'verdict-pass' : 'verdict-fail' ?>" data-verdict>
            <?= icon($passed ? 'check-circle' : 'x-circle') ?> <?= $passed ? 'PASSED' : 'NOT PASSED' ?>
        </div>
        <p class="text-muted">
            <?php if ($passed): ?>Congratulations, <?= e($user['first_name']) ?>. You met the <?= (int) $attempt['passing_score'] ?>% passing standard.
            <?php else: ?>A score of <?= (int) $attempt['passing_score'] ?>% is required to pass. Review the domains below and try again when you are ready.<?php endif; ?>
        </p>
    </section>

    <div class="result-stats">
        <div class="stat reveal"><div class="stat-label"><?= icon('check-circle') ?> Correct Answers</div><div class="stat-value"><span data-count="<?= $correct ?>">0</span></div></div>
        <div class="stat reveal" data-delay="70"><div class="stat-label"><?= icon('x-circle') ?> Incorrect Answers</div><div class="stat-value"><span data-count="<?= $total - $correct ?>">0</span></div></div>
        <div class="stat reveal" data-delay="140"><div class="stat-label"><?= icon('list') ?> Questions</div><div class="stat-value"><span data-count="<?= $total ?>">0</span></div></div>
        <div class="stat reveal" data-delay="210"><div class="stat-label"><?= icon('clock') ?> Time Used</div><div class="stat-value mono"><?= e(fmt_duration((int) $attempt['time_used_seconds'])) ?></div></div>
    </div>

    <div class="card reveal">
        <dl class="kv">
            <dt>Certification</dt><dd><?= e($certification['name']) ?></dd>
            <dt>Score</dt><dd><?= e(fmt_score($score)) ?> (<?= $correct ?> / <?= $total ?>)</dd>
            <dt>Result</dt><dd><?= $passed ? '<span class="badge badge-success">PASSED</span>' : '<span class="badge badge-danger">NOT PASSED</span>' ?></dd>
            <dt>Date</dt><dd><?= e(fmt_date($attempt['submitted_at'], 'j F Y, H:i')) ?> UTC</dd>
            <dt>Duration</dt><dd><?= e(fmt_duration((int) $attempt['time_used_seconds'])) ?> of <?= (int) $attempt['duration_minutes'] ?> minutes<?= $attempt['status'] === 'expired' ? ' · submitted automatically when time ran out' : '' ?></dd>
            <dt>Questions answered</dt><dd><?= $answered ?> / <?= $total ?></dd>
        </dl>
    </div>

    <?php if ($certificate): ?>
        <div class="divider"></div>
        <div class="cert-ready reveal">
            <div class="list-icon"><?= icon('award') ?></div>
            <div class="meta spacer">
                <h3>Your certificate is ready</h3>
                <p class="text-muted">Certificate ID <span class="mono"><?= e($certificate['certificate_code']) ?></span></p>
            </div>
            <div class="row">
                <a class="btn btn-primary" href="<?= e(path('certificate-download.php', ['id' => $certificate['certificate_code']])) ?>"><?= icon('download') ?> Download PDF</a>
                <a class="btn btn-outline" href="<?= e(path('certificate.php', ['id' => $certificate['certificate_code']])) ?>"><?= icon('eye') ?> View certificate</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="divider"></div>
    <div class="card reveal">
        <div class="card-header"><h2>Performance by domain</h2></div>
        <div class="breakdown">
            <?php foreach ($breakdown as $b): $pct = $b['total'] ? round($b['correct'] * 100 / $b['total']) : 0; ?>
                <div class="breakdown-row">
                    <span title="<?= e($b['category']) ?>"><?= e($b['category']) ?></span>
                    <div class="meter"><i class="<?= $pct < (int) $attempt['passing_score'] ? 'is-low' : '' ?>" data-width="<?= (int) $pct ?>"></i></div>
                    <b><?= (int) $b['correct'] ?>/<?= (int) $b['total'] ?></b>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cert-toolbar">
        <a class="btn btn-outline" href="<?= e(path('dashboard.php')) ?>"><?= icon('grid') ?> Back to dashboard</a>
        <?php if (!$passed): ?>
            <a class="btn btn-primary" href="<?= e(path('checkout.php', ['certification' => $certification['slug']])) ?>">Retake the exam <?= icon('arrow-right', 'icon icon-arrow') ?></a>
        <?php endif; ?>
    </div>
</div>
<?php if ($passed): ?><canvas class="confetti" data-confetti aria-hidden="true"></canvas><?php endif; ?>
