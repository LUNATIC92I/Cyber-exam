<?php
use App\Core\View;
use App\Models\Certification;

$v = fn (string $k, $default = '') => $old[$k] ?? ($cert[$k] ?? $default);
$err = fn (string $n) => View::partial('field-error', ['errors' => $errors, 'name' => $n]);
$topics = $old['topics'] ?? implode(', ', $cert['topics'] ?? []);
$price = $old['price'] ?? number_format(($cert['price_cents'] ?? 1000) / 100, 2, '.', '');
?>
<div class="row"><a class="btn btn-ghost btn-sm" href="<?= e(path('admin/certifications.php')) ?>"><?= icon('arrow-left') ?> Certifications</a></div>
<form class="card form" method="post" action="<?= e(path('admin/certifications.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($cert['id'] ?? 0) ?>">
    <div class="form-grid">
        <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" required value="<?= e($v('name')) ?>"><?= $err('name') ?></div>
        <div class="field"><label for="code">Code (used in certificate IDs)</label><input class="input mono" id="code" name="code" required maxlength="10" value="<?= e($v('code')) ?>"><?= $err('code') ?></div>
        <div class="field"><label for="slug">Slug (URL)</label><input class="input mono" id="slug" name="slug" required value="<?= e($v('slug')) ?>"><?= $err('slug') ?></div>
        <div class="field"><label for="accent">Card accent</label>
            <select class="select" id="accent" name="accent"><?php foreach (Certification::ACCENTS as $a): ?><option value="<?= e($a) ?>" <?= $v('accent', 'blue') === $a ? 'selected' : '' ?>><?= e(ucfirst($a)) ?></option><?php endforeach; ?></select></div>
        <div class="field full"><label for="tagline">Tagline</label><input class="input" id="tagline" name="tagline" maxlength="255" value="<?= e($v('tagline')) ?>"><?= $err('tagline') ?></div>
        <div class="field full"><label for="description">Description</label><textarea class="textarea" id="description" name="description"><?= e($v('description')) ?></textarea></div>
        <div class="field full"><label for="topics">Topics (comma separated)</label><input class="input" id="topics" name="topics" value="<?= e($topics) ?>"></div>
        <div class="field"><label for="price">Price</label><input class="input" id="price" name="price" inputmode="decimal" value="<?= e($price) ?>"><?= $err('price') ?></div>
        <div class="field"><label for="currency">Currency</label>
            <select class="select" id="currency" name="currency"><?php foreach (['EUR', 'USD', 'GBP', 'XAF', 'XOF'] as $cur): ?><option <?= $v('currency', 'EUR') === $cur ? 'selected' : '' ?>><?= $cur ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="duration_minutes">Duration (minutes)</label><input class="input" type="number" min="10" max="600" id="duration_minutes" name="duration_minutes" value="<?= e((string) $v('duration_minutes', 120)) ?>"><?= $err('duration_minutes') ?></div>
        <div class="field"><label for="question_count">Questions per exam</label><input class="input" type="number" min="10" max="200" id="question_count" name="question_count" value="<?= e((string) $v('question_count', 100)) ?>"><?= $err('question_count') ?></div>
        <div class="field"><label for="passing_score">Passing score (%)</label><input class="input" type="number" min="1" max="100" id="passing_score" name="passing_score" value="<?= e((string) $v('passing_score', 70)) ?>"><?= $err('passing_score') ?></div>
        <div class="field"><label for="sort_order">Display order</label><input class="input" type="number" id="sort_order" name="sort_order" value="<?= e((string) $v('sort_order', 0)) ?>"><?= $err('sort_order') ?></div>
        <div class="field full"><label class="check"><input type="checkbox" name="is_active" value="1" <?= (int) $v('is_active', 0) ? 'checked' : '' ?>> Active (visible to candidates and purchasable)</label><?= $err('is_active') ?></div>
    </div>
    <?php if ($stats): ?><p class="text-muted">Active questions: <?php foreach ($stats as $s): ?><?= e(ucfirst($s['difficulty'])) ?> <?= (int) $s['n'] ?> · <?php endforeach; ?></p><?php endif; ?>
    <div class="row"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Save certification</button></div>
</form>
