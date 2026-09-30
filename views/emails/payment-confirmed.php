<h1 style="font-size:22px;color:#ffffff;margin:0 0 14px;">Payment Confirmed</h1>
<p>Hello <?= e($user['first_name']) ?>,</p>
<p>Your payment of <strong style="color:#ffffff;"><?= e(money((int) $payment['amount'], $payment['currency'])) ?></strong> for the <strong style="color:#ffffff;"><?= e($certification['name']) ?></strong> certification exam has been confirmed.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:18px 0;border:1px solid #1b2436;border-radius:10px;">
<tr><td style="padding:10px 14px;color:#8593a8;">Reference</td><td style="padding:10px 14px;color:#ffffff;font-family:monospace;"><?= e($payment['uuid']) ?></td></tr>
<tr><td style="padding:10px 14px;color:#8593a8;">Exam</td><td style="padding:10px 14px;color:#ffffff;"><?= (int) $certification['question_count'] ?> questions · <?= (int) $certification['duration_minutes'] ?> minutes</td></tr>
</table>
<p>You can start your exam whenever you are ready. The timer begins as soon as you start.</p>
<p style="margin:26px 0;"><a href="<?= e(url('dashboard.php')) ?>" style="display:inline-block;padding:13px 24px;border-radius:10px;background:#2f6bff;background-image:linear-gradient(135deg,#2f6bff,#22d3ee);color:#ffffff;text-decoration:none;font-weight:bold;">Go to my dashboard</a></p>
