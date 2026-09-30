<h1 style="font-size:22px;color:#ffffff;margin:0 0 14px;">Your Cyber Exam result is available.</h1>
<p>Hello <?= e($user['first_name']) ?>,</p>
<p>Your result for the <strong style="color:#ffffff;"><?= e($certification['name']) ?></strong> certification exam is now available. You scored <strong style="color:#ffffff;"><?= e(fmt_score($attempt['score_percent'])) ?></strong>; a score of <?= (int) $attempt['passing_score'] ?>% is required to pass.</p>
<p>Your result page includes a breakdown by domain to help you prepare for your next attempt.</p>
<p style="margin:26px 0;"><a href="<?= e(url('result.php', ['attempt' => $attempt['uuid']])) ?>" style="display:inline-block;padding:13px 24px;border-radius:10px;background:#2f6bff;background-image:linear-gradient(135deg,#2f6bff,#22d3ee);color:#ffffff;text-decoration:none;font-weight:bold;">View my result</a></p>
