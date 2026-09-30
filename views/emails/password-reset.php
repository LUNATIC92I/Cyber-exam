<h1 style="font-size:22px;color:#ffffff;margin:0 0 14px;">Reset your password</h1>
<p>Hello <?= e($user['first_name']) ?>,</p>
<p>We received a request to reset the password of your CYBER EXAM account.</p>
<p style="margin:26px 0;"><a href="<?= e($link) ?>" style="display:inline-block;padding:13px 24px;border-radius:10px;background:#2f6bff;background-image:linear-gradient(135deg,#2f6bff,#22d3ee);color:#ffffff;text-decoration:none;font-weight:bold;">Choose a new password</a></p>
<p style="font-size:13px;color:#8593a8;">This link expires in 60 minutes and can be used once. If you did not request a reset, ignore this email — your password stays unchanged.</p>
<p style="font-size:12px;color:#6b7a90;word-break:break-all;"><?= e($link) ?></p>
