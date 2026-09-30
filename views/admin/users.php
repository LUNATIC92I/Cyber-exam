<div class="card">
    <form class="toolbar" method="get">
        <input class="input" name="q" value="<?= e($search) ?>" placeholder="Search name or email">
        <button class="btn btn-outline btn-sm" type="submit"><?= icon('search') ?> Search</button>
        <span class="spacer"></span><span class="text-muted"><?= (int) $list['total'] ?> candidate(s)</span>
    </form>
</div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Candidate</th><th>Email</th><th>Country</th><th>Registered</th><th class="num">Exams</th><th class="num">Certificates</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list['rows'] as $u): ?>
        <tr>
            <td><strong><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
            <td><?= e($u['email']) ?> <?= $u['email_verified_at'] ? '' : '<span class="badge badge-warning">unverified</span>' ?></td>
            <td><?= e(country_name($u['country'])) ?></td>
            <td><?= e(fmt_date($u['created_at'], 'j M Y')) ?></td>
            <td class="num"><?= (int) $u['exams'] ?></td>
            <td class="num"><?= (int) $u['certs'] ?></td>
            <td><?= $u['status'] === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Suspended</span>' ?></td>
            <td><a class="btn btn-ghost btn-sm" href="?id=<?= (int) $u['id'] ?>">Open <?= icon('arrow-right', 'icon icon-sm') ?></a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list['rows']): ?><tr><td colspan="8" class="empty">No candidates found.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?= paginate_links($list['page'], $list['pages']) ?>
