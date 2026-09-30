<h1 style="font-size:22px;color:#ffffff;margin:0 0 14px;">Congratulations! You passed your Cyber Exam certification.</h1>
<p>Hello <?= e($user['first_name']) ?>,</p>
<p>You passed the <strong style="color:#ffffff;"><?= e($certification['name']) ?></strong> certification exam with a score of <strong style="color:#22d3ee;"><?= e(fmt_score($attempt['score_percent'])) ?></strong> (<?= (int) $attempt['correct_count'] ?> / <?= (int) $attempt['total_questions'] ?> correct answers).</p>
<p style="margin:26px 0;"><a href="<?= e(url('result.php', ['attempt' => $attempt['uuid']])) ?>" style="display:inline-block;padding:13px 24px;border-radius:10px;background:#2f6bff;background-image:linear-gradient(135deg,#2f6bff,#22d3ee);color:#ffffff;text-decoration:none;font-weight:bold;">View my result</a></p>
