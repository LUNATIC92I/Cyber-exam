<?php
$icons = ['success' => 'check-circle', 'error' => 'x-circle', 'info' => 'info', 'warning' => 'alert'];
if (!empty($flash)): ?>
<div class="flash-stack" role="status" aria-live="polite" data-flash>
    <?php foreach ($flash as $type => $message): $type = isset($icons[$type]) ? $type : 'info'; ?>
        <div class="alert alert-<?= e($type) ?>" data-toast>
            <?= icon($icons[$type]) ?>
            <span><?= e($message) ?></span>
            <button class="alert-close" type="button" aria-label="Dismiss" data-toast-close><?= icon('x', 'icon icon-sm') ?></button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
