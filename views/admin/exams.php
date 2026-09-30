<div class="card">
    <form class="toolbar" method="get">
        <select class="select" name="certification_id" data-autosubmit aria-label="Certification">
            <option value="">All certifications</option>
            <?php foreach ($certifications as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $filters['certification_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="result" data-autosubmit aria-label="Result">
            <option value="">All results</option>
            <option value="passed" <?= $filters['result'] === 'passed' ? 'selected' : '' ?>>Passed</option>
            <option value="failed" <?= $filters['result'] === 'failed' ? 'selected' : '' ?>>Not passed</option>
            <option value="in_progress" <?= $filters['result'] === 'in_progress' ? 'selected' : '' ?>>In progress</option>
        </select>
        <input class="input" name="q" value="<?= e($filters['search']) ?>" placeholder="Candidate name or email">
        <button class="btn btn-outline btn-sm" type="submit"><?= icon('search') ?> Filter</button>
        <span class="spacer"></span><span class="text-muted"><?= (int) $list['total'] ?> exam(s)</span>
    </form>
</div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Candidate</th><th>Certification</th><th>Started</th><th class="num">Time used</th><th class="num">Score</th><th>Result</th><th>Certificate</th></tr></thead>
    <tbody>
    <?php foreach ($list['rows'] as $a): ?>
        <tr>
            <td><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><br><span class="text-muted"><?= e($a['email']) ?></span></td>
            <td><?= e($a['certification_name']) ?></td>
            <td><?= e(fmt_date($a['started_at'], 'j M Y H:i')) ?></td>
            <td class="num mono"><?= e(fmt_duration($a['time_used_seconds'] !== null ? (int) $a['time_used_seconds'] : null)) ?></td>
            <td class="num"><?= $a['score_percent'] !== null ? e(fmt_score($a['score_percent'])) . ' <span class="text-muted">(' . (int) $a['correct_count'] . '/' . (int) $a['total_questions'] . ')</span>' : '—' ?></td>
            <td><?php if ($a['status'] === 'in_progress'): ?><span class="badge badge-info">In progress</span>
                <?php elseif ($a['passed']): ?><span class="badge badge-success">PASSED</span>
                <?php else: ?><span class="badge badge-danger">NOT PASSED</span><?php endif; ?>
                <?= $a['status'] === 'expired' ? '<span class="badge">auto-submitted</span>' : '' ?></td>
            <td>
                <?php if ($a['certificate_code']): ?><a class="mono" href="<?= e(path('admin/certificates.php', ['q' => $a['certificate_code']])) ?>"><?= e($a['certificate_code']) ?></a>
                <?php elseif ($a['passed'] && $canIssue): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="attempt_id" value="<?= (int) $a['id'] ?>"><button class="btn btn-outline btn-sm" type="submit"><?= icon('award') ?> Generate</button></form>
                <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list['rows']): ?><tr><td colspan="7" class="empty">No exams found.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?= paginate_links($list['page'], $list['pages']) ?>
