<?php
use App\Core\View;
?>
<div class="auth-wrap">
    <?= View::partial('auth-aside', ['headline' => 'Welcome back. Your next certification awaits.']) ?>
    <div class="auth-main">
        <div class="auth-card fade-up">
            <div class="eyebrow">Sign in</div>
            <h1>Candidate sign-in</h1>
            <p class="text-muted">Access your dashboard, exams and certificates.</p>
            <form class="form" method="post" action="<?= e(path('login.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" autocomplete="username" required value="<?= e($old['email'] ?? '') ?>" autofocus>
                    <?= View::partial('field-error', ['errors' => $errors, 'name' => 'email']) ?>
                </div>
                <div class="field">
                    <div class="row"><label for="password">Password</label><span class="spacer"></span><a href="<?= e(path('forgot-password.php')) ?>" class="field-hint">Forgot password?</a></div>
                    <div class="input-group">
                        <input class="input" type="password" id="password" name="password" autocomplete="current-password" required maxlength="128">
                        <button class="input-addon" type="button" aria-label="Show password" data-toggle-password="password"><?= icon('eye') ?></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in <?= icon('arrow-right', 'icon icon-arrow') ?></button>
            </form>
            <p class="auth-footer">New to CYBER EXAM? <a href="<?= e(path('register.php')) ?>">Create an account</a></p>
        </div>
    </div>
</div>
