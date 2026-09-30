<?php
use App\Core\View;

$steps = [
    ['user', 'Create your account', 'Register in under a minute and confirm your email address.'],
    ['layers', 'Choose a certification', 'Pick one of our professional tracks, from SOC operations to security architecture.'],
    ['card', 'Pay €10', 'Secure checkout. Your exam unlocks as soon as the payment is confirmed.'],
    ['clock', 'Take the exam', '100 scenario-based questions, 120 minutes, server-controlled timer.'],
    ['chart', 'Get your result', 'Automated scoring the moment you submit, with a per-domain breakdown.'],
    ['award', 'Receive your certificate', 'Score 70% or more to earn a verifiable digital certificate.'],
];
$features = [
    ['badge-check', 'Professional assessments', 'Scenario-based questions written by practitioners, mapped to real job tasks and responsibilities.'],
    ['list', '100-question exams', 'Every exam draws 100 questions across the full domain, balancing breadth and depth.'],
    ['zap', 'Automated scoring', 'Instant, objective grading on the server. No waiting, no manual review, no ambiguity.'],
    ['key', 'Secure certification', 'Server-side timing, randomised questions and answers, and tamper-evident certificate records.'],
    ['qr', 'Certificate verification', 'Each certificate carries a unique ID and QR code that anyone can verify in seconds.'],
    ['file', 'Digital certificates', 'Download a high-resolution PDF certificate, ready to share with employers and clients.'],
];
?>
<section class="hero" aria-labelledby="hero-title">
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="hero-grid" aria-hidden="true"></div>
    <canvas class="hero-canvas" data-particles aria-hidden="true"></canvas>
    <div class="container">
        <div class="hero-inner">
            <div class="hero-pill fade-up"><span class="badge">NEW</span> <?= (int) $stats['certifications'] ?> professional certification tracks available</div>
            <h1 id="hero-title" class="fade-up" data-delay="80">
                <span class="line">MASTER YOUR</span>
                <span class="line gradient-text">CYBERSECURITY SKILLS</span>
            </h1>
            <p class="hero-sub fade-up" data-delay="160">Professional cybersecurity assessments designed to validate your technical knowledge.</p>
            <div class="hero-ctas fade-up" data-delay="240">
                <a class="btn btn-primary btn-lg" href="#certifications">Explore Certifications <?= icon('arrow-right', 'icon icon-arrow') ?></a>
                <a class="btn btn-outline btn-lg" href="<?= e(path('verify.php')) ?>"><?= icon('qr') ?> Verify a Certificate</a>
            </div>
            <div class="hero-meta fade-up" data-delay="320">
                <div><strong data-count="<?= (int) $stats['certifications'] ?>">0</strong>Certifications</div>
                <div><strong data-count="<?= (int) $stats['questions'] ?>">0</strong>Original questions</div>
                <div><strong data-count="100">0</strong>Questions per exam</div>
                <div><strong><span data-count="70">0</span>%</strong>Passing score</div>
            </div>
        </div>

        <div class="hero-preview reveal" aria-hidden="true">
            <div class="preview-window">
                <div class="preview-bar"><span></span><span></span><span></span><em>cyber-exam / soc-analyst</em></div>
                <div class="preview-body">
                    <div class="preview-q">
                        <div class="q-meta"><span>Question 27 / 100</span><span>Alert triage</span></div>
                        <div class="exam-progress"><div class="exam-progress-bar" data-width="27"></div></div>
                        <h4>A SIEM rule fires on 40 failed logins for one account from 12 countries within 5 minutes, followed by one success. What is the most appropriate first action?</h4>
                        <div class="preview-opt"><b>A</b> Close the alert as a benign password-manager sync</div>
                        <div class="preview-opt is-selected"><b>B</b> Contain the account and review the successful session</div>
                        <div class="preview-opt"><b>C</b> Wait 24 hours to confirm the pattern repeats</div>
                        <div class="preview-opt"><b>D</b> Block all logins from the 12 countries permanently</div>
                    </div>
                    <div class="preview-side">
                        <div>
                            <div class="text-muted">Time remaining</div>
                            <div class="preview-timer" data-demo-timer>01:37:42</div>
                        </div>
                        <div class="mini-grid">
                            <?php for ($i = 1; $i <= 40; $i++): ?><i class="<?= $i < 27 ? 'a' : ($i === 27 ? 'c' : '') ?>"></i><?php endfor; ?>
                        </div>
                        <div class="exam-stat"><span>Answered</span><b>26 / 100</b></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="certifications" aria-labelledby="certs-title">
    <div class="container">
        <div class="section-head reveal">
            <div class="eyebrow">Certifications</div>
            <h2 id="certs-title">Seven professional tracks. One standard of excellence.</h2>
            <p>Each exam contains 100 original, scenario-based questions covering the full scope of the discipline.</p>
        </div>
        <div class="cert-grid">
            <?php foreach ($certifications as $i => $c): ?>
                <?= View::partial('cert-card', ['c' => $c, 'access' => null, 'delay' => ($i % 3) * 90]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt" id="how-it-works" aria-labelledby="how-title">
    <div class="container">
        <div class="section-head reveal">
            <div class="eyebrow">How it works</div>
            <h2 id="how-title">From registration to certificate in six steps</h2>
            <p>A clear, secure path designed around the candidate experience.</p>
        </div>
        <div class="steps">
            <?php foreach ($steps as $i => [$ic, $h, $p]): ?>
                <div class="step reveal" data-delay="<?= ($i % 3) * 90 ?>">
                    <span class="step-num">STEP 0<?= $i + 1 ?></span>
                    <div class="step-icon"><?= icon($ic) ?></div>
                    <h3><?= e($h) ?></h3>
                    <p><?= e($p) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" id="why" aria-labelledby="why-title">
    <div class="container">
        <div class="section-head reveal">
            <div class="eyebrow">Why CYBER EXAM</div>
            <h2 id="why-title">Built for credibility, designed for candidates</h2>
            <p>Rigorous assessments with a modern, distraction-free exam experience.</p>
        </div>
        <div class="features">
            <?php foreach ($features as $i => [$ic, $h, $p]): ?>
                <div class="feature reveal" data-delay="<?= ($i % 3) * 90 ?>">
                    <div class="feature-icon"><?= icon($ic) ?></div>
                    <h3><?= e($h) ?></h3>
                    <p><?= e($p) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="cta-title">
    <div class="container">
        <div class="cta-band reveal">
            <div class="eyebrow">Ready when you are</div>
            <h2 id="cta-title">Prove what you know.</h2>
            <p>Create your account, choose your certification and take the exam today. Certificates are issued instantly for scores of 70% and above.</p>
            <div class="hero-ctas">
                <a class="btn btn-primary btn-lg" href="<?= e(path('register.php')) ?>">Create your account <?= icon('arrow-right', 'icon icon-arrow') ?></a>
                <a class="btn btn-outline btn-lg" href="<?= e(path('certifications.php')) ?>">Browse certifications</a>
            </div>
        </div>
    </div>
</section>
