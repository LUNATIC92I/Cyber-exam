<?php if ($canManage): ?>
<div class="toolbar"><span class="spacer"></span><a class="btn btn-primary btn-sm" href="?edit=new"><?= icon('plus') ?> New certification</a></div>
<?php endif; ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Certification</th><th>Code</th><th class="num">Question bank</th><th class="num">Questions / exam</th><th class="num">Duration</th><th class="num">Pass</th><th class="num">Price</th><th class="num">Exams</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($certifications as $c): ?>
        <tr>
            <td><strong><?= e($c['name']) ?></strong></td>
            <td class="mono"><?= e($c['code']) ?></td>
            <td class="num"><?= (int) $c['bank_size'] ?><?= $c['bank_size'] < $c['question_count'] ? ' <span class="badge badge-warning">incomplete</span>' : '' ?></td>
            <td class="num"><?= (int) $c['question_count'] ?></td>
            <td class="num"><?= (int) $c['duration_minutes'] ?> min</td>
            <td class="num"><?= (int) $c['passing_score'] ?>%</td>
            <td class="num"><?= e(money($c['price_cents'], $c['currency'])) ?></td>
            <td class="num"><?= (int) $c['attempts'] ?></td>
            <td><?= $c['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge">Inactive</span>' ?></td>
            <td class="inline-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(path('admin/questions.php', ['certification_id' => $c['id']])) ?>"><?= icon('file') ?> Questions</a>
                <?php if ($canManage): ?><a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $c['id'] ?>"><?= icon('edit') ?> Edit</a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
