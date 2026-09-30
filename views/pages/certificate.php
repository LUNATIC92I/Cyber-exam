<?php
use App\Core\View;

$c = $certificate;
$revoked = $status === 'revoked';
?>
<section class="page-head text-center">
    <div class="container">
        <div class="eyebrow fade-up"><?= $revoked ? 'Revoked certificate' : 'Verified certificate' ?></div>
        <h1 class="h2 fade-up" data-delay="60"><?= e($c['candidate_name']) ?></h1>
        <p class="fade-up text-muted" data-delay="120"><?= e($c['certification_name']) ?> · Issued <?= e(fmt_date($c['issued_at'])) ?> · <span class="mono"><?= e($c['certificate_code']) ?></span></p>
    </div>
</section>
<div class="container">
    <div class="cert-view">
        <div class="cert-paper" data-cert-paper>
            <div class="cert-inner">
                <div class="cert-brand"><?= View::partial('logo', ['class' => '']) ?> CYBER EXAM</div>
                <div class="cert-title">CERTIFICATE OF ACHIEVEMENT</div>
                <div class="cert-rule"></div>
                <p class="serif">This certificate is proudly presented to</p>
                <div class="cert-name"><?= e($c['candidate_name']) ?></div>
                <p class="serif">for successfully completing the</p>
                <div class="cert-exam"><?= e($c['certification_name']) ?> Certification Exam</div>
                <div class="cert-strip">
                    <div><small>SCORE</small><b><?= e(fmt_score($c['score_percent'])) ?></b></div>
                    <div><small>DATE</small><b><?= e(fmt_date($c['issued_at'])) ?></b></div>
                    <div><small>CERTIFICATE ID</small><b class="mono"><?= e($c['certificate_code']) ?></b></div>
                    <div><small>STATUS</small><b class="<?= $revoked ? 'bad' : 'ok' ?>"><?= $revoked ? 'REVOKED' : 'VERIFIED' ?></b></div>
                </div>
                <div class="cert-bottom">
                    <div>
                        <div class="cert-sign">Cyber Exam</div>
                        <div class="cert-sign-line"></div>
                        <div class="cert-sign-label">CYBER EXAM</div>
                        <div class="cert-sign-sub">Authorized Signature</div>
                    </div>
                    <div>
                        <svg class="cert-seal" viewBox="0 0 120 120" aria-label="CYBER EXAM official seal" role="img">
                            <defs>
                                <linearGradient id="seal-g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f6bff"/><stop offset="1" stop-color="#22d3ee"/></linearGradient>
                                <path id="seal-top" d="M 22 60 A 38 38 0 0 1 98 60"/>
                                <path id="seal-bottom" d="M 16 60 A 44 44 0 0 0 104 60"/>
                            </defs>
                            <?php
                            $pts = [];
                            for ($i = 0; $i < 88; $i++) {
                                $a = M_PI * $i / 44;
                                $r = $i % 2 === 0 ? 58 : 53.5;
                                $pts[] = round(60 + $r * cos($a), 2) . ',' . round(60 + $r * sin($a), 2);
                            }
                            ?>
                            <polygon points="<?= implode(' ', $pts) ?>" fill="#2f6bff"/>
                            <circle cx="60" cy="60" r="51" fill="#080d1c"/>
                            <circle cx="60" cy="60" r="48" fill="none" stroke="#22d3ee" stroke-width="0.9"/>
                            <circle cx="60" cy="60" r="33" fill="none" stroke="#3c5aa0" stroke-width="0.6"/>
                            <text font-family="Helvetica, Arial, sans-serif" font-weight="700" font-size="7.4" fill="#fff" letter-spacing="0.9"><textPath href="#seal-top" startOffset="50%" text-anchor="middle">CYBER EXAM • OFFICIAL SEAL</textPath></text>
                            <text font-family="Helvetica, Arial, sans-serif" font-weight="700" font-size="6.6" fill="#22d3ee" letter-spacing="0.9"><textPath href="#seal-bottom" startOffset="50%" text-anchor="middle">VERIFIED CERTIFICATION</textPath></text>
                            <rect x="45" y="41" width="30" height="30" rx="8" fill="url(#seal-g)"/>
                            <path d="M52.5 56 L58 61.8 L68 50.5" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                            <text x="60" y="84" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-weight="700" font-size="8" fill="#fff" letter-spacing="1.5"><?= e(substr($c['issued_at'], 0, 4)) ?></text>
                        </svg>
                    </div>
                    <div>
                        <div class="cert-qr"><?= $qrSvg ?></div>
                        <div class="cert-qr-label">SCAN TO VERIFY</div>
                    </div>
                </div>
                <?php if ($revoked): ?><div class="cert-revoked">REVOKED</div><?php endif; ?>
            </div>
        </div>
        <div class="cert-toolbar">
            <a class="btn btn-outline" href="<?= e(path('verify.php', ['id' => $c['certificate_code']])) ?>"><?= icon('badge-check') ?> Verification details</a>
            <?php if ($canDownload): ?>
                <a class="btn btn-primary" href="<?= e(path('certificate-download.php', ['id' => $c['certificate_code']])) ?>"><?= icon('download') ?> Download PDF</a>
            <?php endif; ?>
        </div>
    </div>
</div>
