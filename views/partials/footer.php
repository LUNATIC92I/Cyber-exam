<?php use App\Core\View; ?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="brand" href="<?= e(path('/')) ?>"><?= View::partial('logo') ?><span>CYBER EXAM</span></a>
                <p class="text-muted">Professional cybersecurity assessments designed to validate your technical knowledge.</p>
            </div>
            <div>
                <h4>Platform</h4>
                <ul>
                    <li><a href="<?= e(path('certifications.php')) ?>">Certifications</a></li>
                    <li><a href="<?= e(path('index.php#how-it-works')) ?>">How it works</a></li>
                    <li><a href="<?= e(path('register.php')) ?>">Create an account</a></li>
                </ul>
            </div>
            <div>
                <h4>Trust</h4>
                <ul>
                    <li><a href="<?= e(path('verify.php')) ?>">Verify a certificate</a></li>
                    <li><a href="<?= e(path('index.php#why')) ?>">Why CYBER EXAM</a></li>
                    <li><a href="<?= e(path('login.php')) ?>">Candidate sign-in</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> CYBER EXAM. All rights reserved.</span>
            <span>Independent cybersecurity certification platform.</span>
        </div>
    </div>
</footer>
