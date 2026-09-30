<?php if (!empty($errors[$name])): ?>
<span class="field-error" id="<?= e($name) ?>-error"><?= icon('alert', 'icon icon-sm') ?><?= e($errors[$name]) ?></span>
<?php endif; ?>
