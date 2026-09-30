<?php
use App\Core\View;
use App\Models\Admin;

$s = fn (string $k) => $old[$k] ?? ($settings[$k] ?? '');
$err = fn (string $n) => View::partial('field-error', ['errors' => $errors, 'name' => $n]);
?>
<form class="card form" method="post" action="<?= e(path('admin/settings.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="card-header"><h2>Exam &amp; platform settings</h2></div>
    <div class="form-grid">
        <div class="field"><label for="default_duration_minutes">Default exam duration (minutes)</label><input class="input" type="number" min="10" max="600" id="default_duration_minutes" name="default_duration_minutes" value="<?= e($s('default_duration_minutes')) ?>"><?= $err('default_duration_minutes') ?></div>
        <div class="field"><label for="questions_per_exam">Questions per exam</label><input class="input" type="number" min="10" max="200" id="questions_per_exam" name="questions_per_exam" value="<?= e($s('questions_per_exam')) ?>"><?= $err('questions_per_exam') ?></div>
        <div class="field"><label for="default_price">Default price</label><input class="input" id="default_price" name="default_price" inputmode="decimal" value="<?= e($old['default_price'] ?? number_format(((int) $settings['default_price_cents']) / 100, 2, '.', '')) ?>"><?= $err('default_price_cents') ?></div>
        <div class="field"><label for="default_currency">Currency</label>
            <select class="select" id="default_currency" name="default_currency"><?php foreach (['EUR', 'USD', 'GBP', 'XAF', 'XOF'] as $c): ?><option <?= $s('default_currency') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="passing_score">Passing score (%)</label><input class="input" type="number" min="1" max="100" id="passing_score" name="passing_score" value="<?= e($s('passing_score')) ?>"><?= $err('passing_score') ?></div>
        <div class="field"><label for="support_email">Support email</label><input class="input" type="email" id="support_email" name="support_email" value="<?= e($s('support_email')) ?>"><?= $err('support_email') ?></div>
        <div class="field"><label class="check"><input type="checkbox" name="require_email_verification" value="1" <?= $s('require_email_verification') === '1' ? 'checked' : '' ?>> Require a verified email before payment and exams</label></div>
        <div class="field"><label class="check"><input type="checkbox" name="registration_open" value="1" <?= $s('registration_open') === '1' ? 'checked' : '' ?>> Registrations open</label></div>
        <div class="field full"><label class="check"><input type="checkbox" name="apply_all" value="1"> Also apply duration, price, currency, passing score and questions per exam to <strong>all existing certifications</strong> (exams already started keep their original settings)</label></div>
    </div>
    <div class="row"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Save settings</button></div>
</form>

<div class="two-col">
    <div class="card">
        <div class="card-header"><h2>Administrators</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr><td><strong><?= e($a['display_name']) ?></strong></td><td><?= e($a['email']) ?></td><td><?= e(Admin::ROLES[$a['role']] ?? $a['role']) ?></td>
                    <td><?= e(fmt_date($a['last_login_at'], 'j M Y H:i')) ?></td>
                    <td><?= $a['status'] === 'active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Disabled</span>' ?></td>
                    <td><?php if ((int) $a['id'] !== (int) $admin['id']): ?>
                        <form method="post" data-confirm="Change this administrator's status?"><?= csrf_field() ?><input type="hidden" name="do" value="toggle_admin"><input type="hidden" name="admin_id" value="<?= (int) $a['id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit"><?= $a['status'] === 'active' ? 'Disable' : 'Enable' ?></button></form>
                    <?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <form class="card form" method="post" action="<?= e(path('admin/settings.php')) ?>" novalidate>
        <?= csrf_field() ?><input type="hidden" name="do" value="add_admin">
        <div class="card-header"><h2>Add an administrator</h2></div>
        <div class="field"><label for="display_name">Display name</label><input class="input" id="display_name" name="display_name" required value="<?= e($old['display_name'] ?? '') ?>"><?= $err('display_name') ?></div>
        <div class="field"><label for="a_email">Email</label><input class="input" type="email" id="a_email" name="email" required value="<?= e($old['email'] ?? '') ?>"><?= $err('email') ?></div>
        <div class="field"><label for="role">Role</label><select class="select" id="role" name="role"><?php foreach (Admin::ROLES as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="a_password">Temporary password</label><input class="input" type="password" id="a_password" name="password" required autocomplete="new-password"><?= $err('password') ?></div>
        <button class="btn btn-outline" type="submit"><?= icon('plus') ?> Create administrator</button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h2>Audit log (latest 30)</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Time (UTC)</th><th>Actor</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($audit as $l): ?>
            <tr><td><?= e(fmt_date($l['created_at'], 'j M Y H:i:s')) ?></td><td><?= e($l['actor_type'] . ($l['actor_id'] ? ' #' . $l['actor_id'] : '')) ?></td>
                <td class="mono"><?= e($l['action']) ?></td><td><?= e(trim(($l['entity_type'] ?? '') . ' ' . ($l['entity_id'] ?? ''))) ?: '—' ?></td><td class="mono"><?= e($l['ip_address'] ?? '—') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
