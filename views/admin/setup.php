<?php
use App\Core\View;

$f = fn (string $n) => View::partial('field-error', ['errors' => $errors, 'name' => $n]);
?>
<div class="eyebrow">Initial setup</div>
<h1 class="h2">Create the super-administrator</h1>
<p class="text-muted">This page works only once, while no administrator exists and ADMIN_SETUP_TOKEN is set.</p>
<form class="form" method="post" action="<?= e(path('admin/setup.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field"><label for="setup_token">Setup token</label><input class="input" type="password" id="setup_token" name="setup_token" required><?= $f('setup_token') ?></div>
    <div class="field"><label for="display_name">Display name</label><input class="input" id="display_name" name="display_name" required value="<?= e($old['display_name'] ?? '') ?>"><?= $f('display_name') ?></div>
    <div class="field"><label for="email">Email</label><input class="input" type="email" id="email" name="email" required value="<?= e($old['email'] ?? '') ?>"><?= $f('email') ?></div>
    <div class="field"><label for="password">Password</label><input class="input" type="password" id="password" name="password" required autocomplete="new-password"><?= $f('password') ?></div>
    <div class="field"><label for="password_confirmation">Confirm password</label><input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"><?= $f('password_confirmation') ?></div>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
</form>
