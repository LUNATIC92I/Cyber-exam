<?php $badge = ['paid' => 'badge-success', 'pending' => 'badge-warning', 'failed' => 'badge-danger', 'refunded' => 'badge-info']; ?>
<div class="card">
    <form class="toolbar" method="get">
        <select class="select" name="status" data-autosubmit aria-label="Status">
            <option value="">All statuses</option>
            <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?><option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select>
        <input class="input" name="q" value="<?= e($filters['search']) ?>" placeholder="Email, transaction or reference">
        <button class="btn btn-outline btn-sm" type="submit"><?= icon('search') ?> Filter</button>
        <span class="spacer"></span>
        <span class="badge badge-success">Total revenue <?= e(money($revenue)) ?></span>
    </form>
</div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Created</th><th>Candidate</th><th>Certification</th><th class="num">Amount</th><th>Provider</th><th>Transaction</th><th>Status</th><th>Paid at</th><th>Exam</th></tr></thead>
    <tbody>
    <?php foreach ($list['rows'] as $p): ?>
        <tr>
            <td><?= e(fmt_date($p['created_at'], 'j M Y H:i')) ?></td>
            <td><strong><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong><br><span class="text-muted"><?= e($p['email']) ?></span></td>
            <td><?= e($p['certification_name']) ?></td>
            <td class="num"><?= e(money((int) $p['amount'], $p['currency'])) ?></td>
            <td><?= e($p['provider']) ?></td>
            <td class="mono"><div class="truncate" title="<?= e($p['transaction_id'] ?? '') ?>"><?= e($p['transaction_id'] ?? '—') ?></div></td>
            <td><span class="badge <?= $badge[$p['status']] ?? '' ?>"><?= e(ucfirst($p['status'])) ?></span><?= $p['failure_reason'] ? '<br><span class="text-muted">' . e($p['failure_reason']) . '</span>' : '' ?></td>
            <td><?= e(fmt_date($p['paid_at'], 'j M Y H:i')) ?></td>
            <td><?= $p['attempt_uuid'] ? '<span class="badge badge-blue">used</span>' : ($p['status'] === 'paid' ? '<span class="badge">available</span>' : '—') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list['rows']): ?><tr><td colspan="9" class="empty">No payments found.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?= paginate_links($list['page'], $list['pages']) ?>
