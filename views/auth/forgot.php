<?php use App\Core\View; ?>
<div class="auth-wrap">
    <?= View::partial('auth-aside', ['headline' => 'Regain access to your account securely.']) ?>
    <div class="auth-main">
        <div class="auth-card fade-up">
            <div class="eyebrow">Password reset</div>
            <h1>Forgot your password?</h1>
            <p class="text-muted">Enter your email address and we will send you a secure reset link valid for 60 minutes.</p>
            <form class="form" method="post" action="<?= e(path('forgot-password.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" autocomplete="email" required value="<?= e($old['email'] ?? '') ?>">
                    <?= View::partial('field-error', ['errors' => $errors, 'name' => 'email']) ?>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Send reset link</button>
            </form>
            <p class="auth-footer"><a href="<?= e(path('login.php')) ?>">Back to sign in</a></p>
        </div>
    </div>
</div>
