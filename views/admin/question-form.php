<?php
use App\Core\View;

$v = fn (string $k, $default = '') => $old[$k] ?? ($q[$k] ?? $default);
$err = fn (string $n) => View::partial('field-error', ['errors' => $errors, 'name' => $n]);
$certId = (int) ($old['certification_id'] ?? ($q['certification_id'] ?? $preset));
?>
<div class="row"><a class="btn btn-ghost btn-sm" href="<?= e(path('admin/questions.php', ['certification_id' => $certId ?: null])) ?>"><?= icon('arrow-left') ?> Question bank</a></div>
<form class="card form" method="post" action="<?= e(path('admin/questions.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($q['id'] ?? 0) ?>">
    <div class="form-grid">
        <div class="field"><label for="certification_id">Certification</label>
            <select class="select" id="certification_id" name="certification_id" required>
                <?php foreach ($certifications as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $certId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select><?= $err('certification_id') ?></div>
        <div class="field"><label for="category">Category (domain)</label><input class="input" id="category" name="category" required maxlength="80" value="<?= e($v('category')) ?>"><?= $err('category') ?></div>
        <div class="field"><label for="difficulty">Difficulty</label>
            <select class="select" id="difficulty" name="difficulty"><?php foreach (['beginner', 'intermediate', 'advanced'] as $d): ?><option value="<?= $d ?>" <?= $v('difficulty', 'intermediate') === $d ? 'selected' : '' ?>><?= e(difficulty_label($d)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" <?= (int) $v('is_active', 1) ? 'checked' : '' ?>> Active (eligible for new exams)</label></div>
        <div class="field full"><label for="question">Question</label><textarea class="textarea" id="question" name="question" required><?= e($v('question')) ?></textarea><?= $err('question') ?></div>
        <div class="field full">
            <span class="label">Answers — select the correct one</span>
            <?php foreach (['A', 'B', 'C', 'D'] as $L): $k = 'option_' . strtolower($L); ?>
                <div class="option-row">
                    <input type="radio" name="correct_option" value="<?= $L ?>" aria-label="Answer <?= $L ?> is correct" <?= $v('correct_option', 'A') === $L ? 'checked' : '' ?>>
                    <input class="input" name="<?= $k ?>" placeholder="Answer <?= $L ?>" maxlength="500" required value="<?= e($v($k)) ?>" aria-label="Answer <?= $L ?>">
                </div>
            <?php endforeach; ?>
            <?= $err('option_a') ?><?= $err('option_b') ?><?= $err('option_c') ?><?= $err('option_d') ?><?= $err('correct_option') ?>
        </div>
        <div class="field full"><label for="explanation">Explanation</label><textarea class="textarea" id="explanation" name="explanation" required><?= e($v('explanation')) ?></textarea><?= $err('explanation') ?></div>
    </div>
    <div class="row"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Save question</button></div>
</form>
