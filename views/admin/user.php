<div class="row"><a class="btn btn-ghost btn-sm" href="<?= e(path('admin/users.php')) ?>"><?= icon('arrow-left') ?> All candidates</a></div>
<div class="two-col">
    <div class="card">
        <div class="card-header"><div class="avatar"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1))) ?></div><h2><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h2></div>
        <dl class="kv">
            <dt>Email</dt><dd><?= e($user['email']) ?></dd>
            <dt>Email verified</dt><dd><?= $user['email_verified_at'] ? e(fmt_date($user['email_verified_at'], 'j M Y H:i')) : '<span class="badge badge-warning">No</span>' ?></dd>
            <dt>Country</dt><dd><?= e(country_name($user['country'])) ?></dd>
            <dt>Registered</dt><dd><?= e(fmt_date($user['created_at'], 'j M Y H:i')) ?></dd>
            <dt>Last sign-in</dt><dd><?= e(fmt_date($user['last_login_at'], 'j M Y H:i')) ?></dd>
            <dt>Status</dt><dd><?= e(ucfirst($user['status'])) ?></dd>
        </dl>
        <?php if ($canManage): ?>
            <div class="divider"></div>
            <div class="inline-actions">
                <form method="post" data-confirm="<?= $user['status'] === 'active' ? 'Suspend this account? The candidate will be signed out.' : 'Reactivate this account?' ?>">
                    <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                    <input type="hidden" name="do" value="<?= $user['status'] === 'active' ? 'suspend' : 'activate' ?>">
                    <button class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>" type="submit"><?= $user['status'] === 'active' ? 'Suspend account' : 'Reactivate account' ?></button>
                </form>
                <?php if (!$user['email_verified_at']): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="do" value="verify_email">
                        <button class="btn btn-sm btn-outline" type="submit">Mark email verified</button></form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header"><h2>Statistics</h2></div>
        <dl class="kv">
            <dt>Exams taken</dt><dd><?= (int) $stats['taken'] ?></dd>
            <dt>Passed / failed</dt><dd><?= (int) $stats['passed'] ?> / <?= (int) $stats['failed'] ?></dd>
            <dt>Average score</dt><dd><?= e(fmt_score($stats['average'])) ?></dd>
            <dt>Certificates</dt><dd><?= (int) $stats['certificates'] ?></dd>
        </dl>
    </div>
</div>
<div class="card">
    <div class="card-header"><h2>Exams</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Certification</th><th>Submitted</th><th class="num">Score</th><th>Result</th><th>Certificate</th></tr></thead>
        <tbody>
        <?php foreach ($attempts as $a): ?>
            <tr><td><?= e($a['certification_name']) ?></td><td><?= e(fmt_date($a['submitted_at'], 'j M Y H:i')) ?></td><td class="num"><?= e(fmt_score($a['score_percent'])) ?></td>
                <td><?= $a['passed'] ? '<span class="badge badge-success">PASSED</span>' : '<span class="badge badge-danger">NOT PASSED</span>' ?></td>
                <td class="mono"><?= e($a['certificate_code'] ?? '—') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$attempts): ?><tr><td colspan="5" class="empty">No exams.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<div class="card">
    <div class="card-header"><h2>Payments</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Certification</th><th class="num">Amount</th><th>Provider</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr><td><?= e(fmt_date($p['created_at'], 'j M Y H:i')) ?></td><td><?= e($p['certification_name']) ?></td><td class="num"><?= e(money((int) $p['amount'], $p['currency'])) ?></td><td><?= e($p['provider']) ?></td><td><?= e(ucfirst($p['status'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="5" class="empty">No payments.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
