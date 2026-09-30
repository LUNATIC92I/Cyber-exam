<?php
$cards = [
    ['users', 'Users', number_format($kpi['users'])],
    ['list', 'Exams', number_format($kpi['exams'])],
    ['euro', 'Revenue', money($kpi['revenue'])],
    ['award', 'Certificates', number_format($kpi['certificates'])],
    ['card', 'Payments', number_format($kpi['payments'])],
    ['file', 'Questions', number_format($kpi['questions'])],
    ['layers', 'Certifications', number_format($kpi['certifications'])],
    ['percent', 'Pass rate', number_format($kpi['pass_rate'], 1) . '%'],
];
// Revenue line chart (SVG, no library)
$w = 760; $h = 200; $pad = 28;
$max = max(1000, max(array_column($series, 'value')));
$n = count($series);
$pts = [];
foreach ($series as $i => $p) {
    $pts[] = [round($pad + $i * ($w - 2 * $pad) / max(1, $n - 1), 1), round($h - $pad - ($p['value'] / $max) * ($h - 2 * $pad), 1)];
}
$line = 'M' . implode(' L', array_map(fn ($p) => $p[0] . ',' . $p[1], $pts));
$area = $line . " L{$pts[$n - 1][0]}," . ($h - $pad) . " L{$pts[0][0]}," . ($h - $pad) . ' Z';
?>
<div class="kpis">
    <?php foreach ($cards as $i => [$ic, $label, $value]): ?>
        <div class="stat reveal" data-delay="<?= $i * 50 ?>"><div class="stat-label"><?= icon($ic) ?> <?= e($label) ?></div><div class="stat-value"><?= e($value) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="two-col">
    <div class="card">
        <div class="card-header"><h2>Revenue — last 30 days</h2><span class="spacer"></span><span class="badge badge-info"><?= (int) $kpi['in_progress'] ?> exam(s) in progress</span></div>
        <svg class="line-chart" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none" role="img" aria-label="Revenue over the last 30 days">
            <defs><linearGradient id="rev-grad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2f6bff" stop-opacity=".45"/><stop offset="1" stop-color="#2f6bff" stop-opacity="0"/></linearGradient></defs>
            <?php for ($g = 0; $g <= 4; $g++): $gy = $pad + $g * ($h - 2 * $pad) / 4; ?>
                <line class="grid" x1="<?= $pad ?>" x2="<?= $w - $pad ?>" y1="<?= $gy ?>" y2="<?= $gy ?>"/>
                <text x="2" y="<?= $gy + 3 ?>"><?= e(money((int) round($max * (1 - $g / 4)))) ?></text>
            <?php endfor; ?>
            <path class="area" d="<?= $area ?>"/>
            <path class="line" d="<?= $line ?>"/>
            <?php foreach ([0, 10, 20, 29] as $i): ?><text x="<?= $pts[$i][0] - 12 ?>" y="<?= $h - 8 ?>"><?= e($series[$i]['label']) ?></text><?php endforeach; ?>
        </svg>
    </div>
    <div class="card">
        <div class="card-header"><h2>Recent activity</h2></div>
        <div class="list log-list">
            <?php foreach ($recent as $r): ?>
                <div class="list-item"><div class="meta"><strong class="mono"><?= e($r['action']) ?></strong><span><?= e($r['actor_type']) ?> · <?= e(fmt_date($r['created_at'], 'j M H:i')) ?><?= $r['entity_id'] ? ' · ' . e($r['entity_type'] . ' ' . $r['entity_id']) : '' ?></span></div></div>
            <?php endforeach; ?>
            <?php if (!$recent): ?><div class="empty">No activity yet.</div><?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Results by certification</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Certification</th><th class="num">Exams</th><th class="num">Passed</th><th class="num">Pass rate</th><th class="num">Average score</th></tr></thead>
        <tbody>
        <?php foreach ($perCert as $c): ?>
            <tr><td><strong><?= e($c['name']) ?></strong></td><td class="num"><?= (int) $c['attempts'] ?></td><td class="num"><?= (int) $c['passed'] ?></td>
                <td class="num"><?= $c['attempts'] ? number_format($c['passed'] * 100 / $c['attempts'], 1) . '%' : '—' ?></td>
                <td class="num"><?= $c['avg_score'] !== null ? e(fmt_score(round((float) $c['avg_score'], 1))) : '—' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
