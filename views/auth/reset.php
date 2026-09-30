<?php use App\Core\View; ?>
<div class="auth-wrap">
    <?= View::partial('auth-aside', ['headline' => 'Choose a strong new password.']) ?>
    <div class="auth-main">
        <div class="auth-card fade-up">
            <div class="eyebrow">Password reset</div>
            <h1>Set a new password</h1>
            <form class="form" method="post" action="<?= e(path('reset-password.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                    <label for="password">New password</label>
                    <div class="input-group">
                        <input class="input" type="password" id="password" name="password" autocomplete="new-password" required minlength="10" maxlength="128" data-strength>
                        <button class="input-addon" type="button" aria-label="Show password" data-toggle-password="password"><?= icon('eye') ?></button>
                    </div>
                    <div class="strength" aria-hidden="true"><div class="strength-bar" data-strength-bar></div></div>
                    <span class="field-hint">At least 10 characters with upper and lower case letters, a number and a symbol.</span>
                    <?= View::partial('field-error', ['errors' => $errors, 'name' => 'password']) ?>
                </div>
                <div class="field<?= isset($errors['password_confirmation']) ? ' has-error' : '' ?>">
                    <label for="password_confirmation">Confirm new password</label>
                    <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required maxlength="128">
                    <?= View::partial('field-error', ['errors' => $errors, 'name' => 'password_confirmation']) ?>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Update password</button>
            </form>
        </div>
    </div>
</div>
