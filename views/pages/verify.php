<?php
$status = $result['status'] ?? null;
$c = $result['certificate'] ?? null;
?>
<section class="page-head text-center">
    <div class="container container-sm">
        <div class="eyebrow fade-up">Public verification</div>
        <h1 class="fade-up" data-delay="60">VERIFY A CERTIFICATE</h1>
        <p class="fade-up" data-delay="120" style-none>Enter the Certificate ID printed on a CYBER EXAM certificate, or scan its QR code, to confirm it was genuinely issued.</p>
    </div>
</section>
<div class="container">
    <div class="verify-box">
        <form class="verify-form fade-up" data-delay="160" method="get" action="<?= e(path('verify.php')) ?>" role="search">
            <label class="sr-only" for="certificate-id">Certificate ID</label>
            <input class="input" id="certificate-id" name="id" placeholder="CEX-SOC-2026-XXXXXXXX" value="<?= e($query) ?>" maxlength="64" autocomplete="off" spellcheck="false" required>
            <button class="btn btn-primary btn-lg" type="submit"><?= icon('search') ?> VERIFY</button>
        </form>

        <?php if ($status === 'verified' || $status === 'revoked'): ?>
            <div class="verify-result <?= $status === 'verified' ? 'is-verified' : 'is-revoked' ?>" data-verify-result>
                <div class="verify-status">
                    <div class="status-icon <?= $status === 'verified' ? 'is-success' : 'is-error' ?>"><?= icon($status === 'verified' ? 'check' : 'x') ?></div>
                    <div>
                        <h2><?= $status === 'verified' ? 'VERIFIED' : 'REVOKED' ?></h2>
                        <span class="text-muted"><?= $status === 'verified' ? 'This certificate is authentic and currently valid.' : 'This certificate was issued by CYBER EXAM but has been revoked and is no longer valid.' ?></span>
                    </div>
                </div>
                <dl class="kv">
                    <dt>Certificate ID</dt><dd class="mono"><?= e($c['certificate_code']) ?></dd>
                    <dt>Candidate</dt><dd><?= e($c['candidate_name']) ?></dd>
                    <dt>Certification</dt><dd><?= e($c['certification_name']) ?></dd>
                    <dt>Score</dt><dd><?= e(fmt_score($c['score_percent'])) ?></dd>
                    <dt>Issue Date</dt><dd><?= e(fmt_date($c['issued_at'])) ?></dd>
                    <dt>Status</dt><dd><?= $status === 'verified' ? '<span class="badge badge-success"><span class="dot"></span>Valid</span>' : '<span class="badge badge-danger"><span class="dot"></span>Revoked</span>' ?>
                        <?php if ($status === 'revoked' && $c['revoked_at']): ?><span class="text-muted"> since <?= e(fmt_date($c['revoked_at'])) ?></span><?php endif; ?></dd>
                </dl>
                <div class="cert-toolbar">
                    <a class="btn btn-outline btn-sm" href="<?= e(path('certificate.php', ['id' => $c['certificate_code']])) ?>"><?= icon('eye') ?> View certificate</a>
                </div>
            </div>
        <?php elseif ($status !== null): ?>
            <div class="verify-result is-revoked" data-verify-result>
                <div class="verify-status">
                    <div class="status-icon is-error"><?= icon('alert') ?></div>
                    <div>
                        <h2>NOT FOUND</h2>
                        <span class="text-muted"><?= $status === 'invalid' ? 'This ID is not a valid CYBER EXAM certificate ID. Check the format: CEX-XXX-YYYY-XXXXXXXX.' : 'No certificate matches this ID. It may have been mistyped, or the document is not an authentic CYBER EXAM certificate.' ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card card-sm reveal" data-delay="200">
            <div class="row"><?= icon('info') ?><strong>How verification works</strong></div>
            <p class="text-muted" style-none>Every certificate is checked live against the CYBER EXAM registry and its integrity signature. Changing the ID in a URL or editing a PDF cannot produce a valid result.</p>
        </div>
    </div>
</div>
