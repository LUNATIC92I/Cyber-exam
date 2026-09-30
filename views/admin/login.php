<?php use App\Core\View; ?>
<div class="eyebrow">Back-office</div>
<h1 class="h2">Administrator sign-in</h1>
<form class="form" method="post" action="<?= e(path('admin/login.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" autocomplete="username" required value="<?= e($old['email'] ?? '') ?>">
        <?= View::partial('field-error', ['errors' => $errors, 'name' => 'email']) ?>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
</form>
