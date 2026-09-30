<h1 style="font-size:22px;color:#ffffff;margin:0 0 14px;">Confirm your email address</h1>
<p>Hello <?= e($user['first_name']) ?>,</p>
<p>Welcome to CYBER EXAM. Please confirm your email address to activate payments and exams on your account.</p>
<p style="margin:26px 0;"><a href="<?= e($link) ?>" style="display:inline-block;padding:13px 24px;border-radius:10px;background:#2f6bff;background-image:linear-gradient(135deg,#2f6bff,#22d3ee);color:#ffffff;text-decoration:none;font-weight:bold;">Confirm my email</a></p>
<p style="font-size:13px;color:#8593a8;">This link expires in 48 hours. If you did not create an account, you can safely ignore this email.</p>
<p style="font-size:12px;color:#6b7a90;word-break:break-all;"><?= e($link) ?></p>
