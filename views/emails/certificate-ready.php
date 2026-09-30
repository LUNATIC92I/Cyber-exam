<h1 style="font-size:22px;color:#ffffff;margin:0 0 14px;">Your Cyber Exam certificate is ready.</h1>
<p>Hello <?= e($user['first_name']) ?>,</p>
<p>Your <strong style="color:#ffffff;"><?= e($certificate['certification_name']) ?></strong> certificate has been issued.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:18px 0;border:1px solid #1b2436;border-radius:10px;">
<tr><td style="padding:10px 14px;color:#8593a8;">Certificate ID</td><td style="padding:10px 14px;color:#ffffff;font-family:monospace;"><?= e($certificate['certificate_code']) ?></td></tr>
<tr><td style="padding:10px 14px;color:#8593a8;">Score</td><td style="padding:10px 14px;color:#ffffff;"><?= e(fmt_score($certificate['score_percent'])) ?></td></tr>
<tr><td style="padding:10px 14px;color:#8593a8;">Issue date</td><td style="padding:10px 14px;color:#ffffff;"><?= e(fmt_date($certificate['issued_at'])) ?></td></tr>
</table>
<p style="margin:26px 0;"><a href="<?= e(url('certificate-download.php', ['id' => $certificate['certificate_code']])) ?>" style="display:inline-block;padding:13px 24px;border-radius:10px;background:#2f6bff;background-image:linear-gradient(135deg,#2f6bff,#22d3ee);color:#ffffff;text-decoration:none;font-weight:bold;">Download my certificate</a></p>
<p style="font-size:13px;color:#8593a8;">Anyone can verify this certificate at <a href="<?= e(url('verify.php', ['id' => $certificate['certificate_code']])) ?>" style="color:#22d3ee;"><?= e(url('verify.php')) ?></a>.</p>
