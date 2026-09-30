<?php
use App\Core\View;

$err = fn (string $f) => View::partial('field-error', ['errors' => $errors, 'name' => $f]);
$cls = fn (string $f) => 'field' . (isset($errors[$f]) ? ' has-error' : '');
?>
<div class="auth-wrap">
    <?= View::partial('auth-aside', ['headline' => 'Your certification journey starts here.']) ?>
    <div class="auth-main">
        <div class="auth-card fade-up">
            <div class="eyebrow">Create account</div>
            <h1>Join CYBER EXAM</h1>
            <p class="text-muted">Register to purchase and take professional cybersecurity exams.</p>
            <?php if (!$open): ?>
                <div class="banner"><?= icon('info') ?> Registrations are temporarily closed. Please come back later.</div>
            <?php else: ?>
            <form class="form" method="post" action="<?= e(path('register.php')) ?>" novalidate data-validate>
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="<?= $cls('first_name') ?>">
                        <label for="first_name">First name</label>
                        <input class="input" id="first_name" name="first_name" autocomplete="given-name" required maxlength="80" value="<?= e($old['first_name'] ?? '') ?>">
                        <?= $err('first_name') ?>
                    </div>
                    <div class="<?= $cls('last_name') ?>">
                        <label for="last_name">Last name</label>
                        <input class="input" id="last_name" name="last_name" autocomplete="family-name" required maxlength="80" value="<?= e($old['last_name'] ?? '') ?>">
                        <?= $err('last_name') ?>
                    </div>
                </div>
                <div class="<?= $cls('email') ?>">
                    <label for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" autocomplete="email" required maxlength="191" value="<?= e($old['email'] ?? '') ?>">
                    <?= $err('email') ?>
                </div>
                <div class="<?= $cls('country') ?>">
                    <label for="country">Country</label>
                    <select class="select" id="country" name="country" required autocomplete="country">
                        <option value="">Select your country</option>
                        <?php foreach (countries() as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= ($old['country'] ?? '') === $code ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('country') ?>
                </div>
                <div class="<?= $cls('password') ?>">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <input class="input" type="password" id="password" name="password" autocomplete="new-password" required minlength="10" maxlength="128" data-strength>
                        <button class="input-addon" type="button" aria-label="Show password" data-toggle-password="password"><?= icon('eye') ?></button>
                    </div>
                    <div class="strength" aria-hidden="true"><div class="strength-bar" data-strength-bar></div></div>
                    <span class="field-hint">At least 10 characters with upper and lower case letters, a number and a symbol.</span>
                    <?= $err('password') ?>
                </div>
                <div class="<?= $cls('password_confirmation') ?>">
                    <label for="password_confirmation">Confirm password</label>
                    <input class="input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required maxlength="128">
                    <?= $err('password_confirmation') ?>
                </div>
                <div class="<?= $cls('terms') ?>">
                    <label class="check"><input type="checkbox" name="terms" value="1" <?= !empty($old['terms']) ? 'checked' : '' ?>> I agree to take exams individually, without external assistance, and accept the CYBER EXAM candidate rules.</label>
                    <?= $err('terms') ?>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Create account <?= icon('arrow-right', 'icon icon-arrow') ?></button>
            </form>
            <?php endif; ?>
            <p class="auth-footer">Already registered? <a href="<?= e(path('login.php')) ?>">Sign in</a></p>
        </div>
    </div>
</div>
