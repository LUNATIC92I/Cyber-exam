<div class="card">
    <form class="toolbar" method="get">
        <select class="select" name="certification_id" data-autosubmit aria-label="Certification">
            <option value="">All certifications</option>
            <?php foreach ($certifications as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $filters['certification_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= (int) $c['bank_size'] ?>)</option><?php endforeach; ?>
        </select>
        <select class="select" name="difficulty" data-autosubmit aria-label="Difficulty">
            <option value="">All difficulties</option>
            <?php foreach (['beginner', 'intermediate', 'advanced'] as $d): ?><option value="<?= $d ?>" <?= $filters['difficulty'] === $d ? 'selected' : '' ?>><?= e(difficulty_label($d)) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="status" data-autosubmit aria-label="Status">
            <option value="">Active &amp; archived</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active only</option>
            <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived only</option>
        </select>
        <input class="input" name="q" value="<?= e($filters['search']) ?>" placeholder="Search text or category">
        <button class="btn btn-outline btn-sm" type="submit"><?= icon('search') ?> Filter</button>
        <span class="spacer"></span>
        <?php if ($canManage): ?><a class="btn btn-primary btn-sm" href="?edit=new&amp;certification_id=<?= (int) $filters['certification_id'] ?>"><?= icon('plus') ?> New question</a><?php endif; ?>
    </form>
    <p class="text-muted"><?= (int) $list['total'] ?> question(s)</p>
</div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Question</th><th>Certification</th><th>Category</th><th>Difficulty</th><th>Answer</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list['rows'] as $q): ?>
        <tr>
            <td class="mono"><?= (int) $q['id'] ?></td>
            <td><div class="truncate" title="<?= e($q['question']) ?>"><?= e($q['question']) ?></div></td>
            <td class="mono"><?= e($q['certification_code']) ?></td>
            <td><?= e($q['category']) ?></td>
            <td><span class="badge"><?= e(difficulty_label($q['difficulty'])) ?></span></td>
            <td class="mono"><?= e($q['correct_option']) ?></td>
            <td><?= $q['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge">Archived</span>' ?></td>
            <td class="inline-actions">
                <?php if ($canManage): ?>
                    <a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $q['id'] ?>" aria-label="Edit"><?= icon('edit') ?></a>
                    <form method="post" data-confirm="Delete this question? Questions already used in exams are archived instead.">
                        <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit" aria-label="Delete"><?= icon('trash') ?></button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list['rows']): ?><tr><td colspan="8" class="empty">No questions match these filters.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?= paginate_links($list['page'], $list['pages']) ?>
