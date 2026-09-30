<?php
use App\Core\View;

$statCards = [
    ['list', 'Exams Taken', $stats['taken'], ''],
    ['check-circle', 'Exams Passed', $stats['passed'], ''],
    ['x-circle', 'Exams Failed', $stats['failed'], ''],
    ['award', 'Certificates', $stats['certificates'], ''],
    ['percent', 'Average Score', $stats['average'], '%'],
];
$payBadge = ['paid' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-danger', 'refunded' => 'badge-info'];
?>
<div class="container">
    <div class="dash-head">
        <div class="fade-up">
            <div class="eyebrow">Candidate dashboard</div>
            <h1>Welcome, <?= e($user['first_name']) ?></h1>
            <p>Track your progress, start exams and manage your certificates.</p>
        </div>
        <a class="btn btn-outline fade-up" data-delay="100" href="<?= e(path('verify.php')) ?>"><?= icon('qr') ?> Verify a certificate</a>
    </div>

    <?php if ($needsVerification): ?>
        <div class="banner fade-up">
            <?= icon('mail') ?>
            <span>Please confirm your email address (<?= e($user['email']) ?>) to unlock payments and exams.</span>
            <span class="spacer"></span>
            <form method="post" action="<?= e(path('resend-verification.php')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline" type="submit">Resend email</button>
            </form>
        </div>
        <div class="divider"></div>
    <?php endif; ?>

    <div class="stats">
        <?php foreach ($statCards as $i => [$ic, $label, $value, $suffix]): ?>
            <div class="stat reveal" data-delay="<?= $i * 70 ?>">
                <div class="stat-label"><?= icon($ic) ?> <?= e($label) ?></div>
                <div class="stat-value"><span data-count="<?= e((string) $value) ?>" data-decimals="<?= $suffix === '%' ? 1 : 0 ?>">0</span><?php if ($suffix): ?><small><?= e($suffix) ?></small><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section-title">
        <h2>Available Certifications</h2>
        <span class="text-muted">100 questions · Passing score 70% · Instant results</span>
    </div>
    <div class="cert-grid">
        <?php foreach ($certifications as $i => $c): ?>
            <?= View::partial('cert-card', ['c' => $c, 'access' => $access[$c['id']] ?? ['state' => 'pay'], 'delay' => ($i % 3) * 80]) ?>
        <?php endforeach; ?>
    </div>

    <div class="dash-grid">
        <div class="card reveal">
            <div class="card-header"><h2>Score history</h2><span class="spacer"></span><span class="badge badge-info">Pass mark 70%</span></div>
            <?php if ($chart): ?>
                <div class="bar-chart" data-bar-chart>
                    <div class="bars">
                        <div class="bar-threshold"><span>70%</span></div>
                        <?php foreach ($chart as $bar): ?>
                            <div class="bar">
                                <div class="bar-fill<?= $bar['passed'] ? '' : ' is-fail' ?>" data-height="<?= e((string) $bar['score']) ?>"><span><?= e(fmt_score($bar['score'])) ?></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bar-labels">
                        <?php foreach ($chart as $bar): ?><span><?= e($bar['label']) ?></span><?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty"><?= icon('chart', 'icon-lg') ?>Your scores will appear here after your first exam.</div>
            <?php endif; ?>

            <div class="divider"></div>
            <div class="card-header"><h2>Exam history</h2></div>
            <?php if ($history): ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Certification</th><th>Date</th><th class="num">Score</th><th>Result</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><strong><?= e($h['certification_name']) ?></strong></td>
                                <td><?= e(fmt_date($h['submitted_at'], 'j M Y, H:i')) ?></td>
                                <td class="num"><?= e(fmt_score($h['score_percent'])) ?></td>
                                <td><?= $h['passed'] ? '<span class="badge badge-success">PASSED</span>' : '<span class="badge badge-danger">NOT PASSED</span>' ?></td>
                                <td><a class="btn btn-ghost btn-sm" href="<?= e(path('result.php', ['attempt' => $h['uuid']])) ?>">View <?= icon('arrow-right', 'icon icon-sm') ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty"><?= icon('list', 'icon-lg') ?>No exams taken yet. Choose a certification above to get started.</div>
            <?php endif; ?>
        </div>

        <div class="stack">
            <div class="card reveal" data-delay="80">
                <div class="card-header"><h2>My certificates</h2></div>
                <?php if ($certificates): ?>
                    <div class="list">
                        <?php foreach ($certificates as $c): ?>
                            <div class="list-item">
                                <div class="list-icon"><?= icon('award') ?></div>
                                <div class="meta">
                                    <strong><?= e($c['certification_name']) ?></strong>
                                    <span class="mono"><?= e($c['certificate_code']) ?></span>
                                </div>
                                <?php if ($c['status'] === 'revoked'): ?>
                                    <span class="badge badge-danger">Revoked</span>
                                <?php else: ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(path('certificate-download.php', ['id' => $c['certificate_code']])) ?>" aria-label="Download PDF"><?= icon('download') ?></a>
                                    <a class="btn btn-ghost btn-sm" href="<?= e(path('certificate.php', ['id' => $c['certificate_code']])) ?>" aria-label="View certificate"><?= icon('eye') ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty"><?= icon('award', 'icon-lg') ?>Pass an exam with 70% or more to earn your first certificate.</div>
                <?php endif; ?>
            </div>

            <div class="card reveal" data-delay="140">
                <div class="card-header"><h2>Payments</h2></div>
                <?php if ($payments): ?>
                    <div class="list">
                        <?php foreach (array_slice($payments, 0, 6) as $p): ?>
                            <div class="list-item">
                                <div class="list-icon"><?= icon('card') ?></div>
                                <div class="meta">
                                    <strong><?= e($p['certification_name']) ?></strong>
                                    <span><?= e(fmt_date($p['created_at'], 'j M Y')) ?> · <?= e(money((int) $p['amount'], $p['currency'])) ?></span>
                                </div>
                                <span class="badge <?= $payBadge[$p['status']] ?? '' ?>"><?= e(ucfirst($p['status'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty"><?= icon('card', 'icon-lg') ?>No payments yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
