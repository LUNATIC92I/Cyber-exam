<div class="card">
    <form class="toolbar" method="get">
        <select class="select" name="status" data-autosubmit aria-label="Status">
            <option value="">All statuses</option>
            <option value="valid" <?= $filters['status'] === 'valid' ? 'selected' : '' ?>>Valid</option>
            <option value="revoked" <?= $filters['status'] === 'revoked' ? 'selected' : '' ?>>Revoked</option>
        </select>
        <input class="input" name="q" value="<?= e($filters['search']) ?>" placeholder="Certificate ID, name or email">
        <button class="btn btn-outline btn-sm" type="submit"><?= icon('search') ?> Search</button>
        <span class="spacer"></span><span class="text-muted"><?= (int) $list['total'] ?> certificate(s)</span>
    </form>
</div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Certificate ID</th><th>Candidate</th><th>Certification</th><th class="num">Score</th><th>Issued</th><th class="num">Verifications</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($list['rows'] as $c): ?>
        <tr>
            <td class="mono"><strong><?= e($c['certificate_code']) ?></strong></td>
            <td><?= e($c['candidate_name']) ?><br><span class="text-muted"><?= e($c['email']) ?></span></td>
            <td><?= e($c['certification_name']) ?></td>
            <td class="num"><?= e(fmt_score($c['score_percent'])) ?></td>
            <td><?= e(fmt_date($c['issued_at'], 'j M Y')) ?></td>
            <td class="num"><?= (int) $c['verifications'] ?></td>
            <td><?= $c['status'] === 'valid' ? '<span class="badge badge-success">Valid</span>' : '<span class="badge badge-danger">Revoked</span><br><span class="text-muted">' . e($c['revoked_reason'] ?? '') . '</span>' ?></td>
            <td>
                <div class="inline-actions">
                    <a class="btn btn-ghost btn-sm" href="<?= e(path('certificate.php', ['id' => $c['certificate_code']])) ?>" target="_blank" rel="noopener" aria-label="View"><?= icon('eye') ?></a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(path('certificate-download.php', ['id' => $c['certificate_code']])) ?>" aria-label="Download PDF"><?= icon('download') ?></a>
                    <?php if ($canManage && $c['status'] === 'revoked'): ?>
                        <form method="post" data-confirm="Reinstate this certificate? It will verify as valid again.">
                            <?= csrf_field() ?><input type="hidden" name="do" value="reinstate"><input type="hidden" name="code" value="<?= e($c['certificate_code']) ?>">
                            <button class="btn btn-success btn-sm" type="submit">Reinstate</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if ($canManage && $c['status'] === 'valid'): ?>
                    <details class="revoke-box"><summary class="btn btn-danger btn-sm">Revoke…</summary>
                        <form class="revoke-form" method="post" data-confirm="Revoke this certificate? Public verification will show REVOKED.">
                            <?= csrf_field() ?><input type="hidden" name="do" value="revoke"><input type="hidden" name="code" value="<?= e($c['certificate_code']) ?>">
                            <input class="input" name="reason" required minlength="5" maxlength="255" placeholder="Reason (e.g. exam misconduct)">
                            <button class="btn btn-danger btn-sm" type="submit">Confirm</button>
                        </form>
                    </details>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list['rows']): ?><tr><td colspan="8" class="empty">No certificates found.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?= paginate_links($list['page'], $list['pages']) ?>
