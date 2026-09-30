<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($subject ?? 'CYBER EXAM') ?></title>
</head>
<body style="margin:0;padding:0;background:#04060b;font-family:Helvetica,Arial,sans-serif;color:#e6edf7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#04060b;padding:32px 12px;">
<tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#0b1120;border:1px solid #1b2436;border-radius:16px;overflow:hidden;">
        <tr><td style="height:4px;background:#2f6bff;background-image:linear-gradient(90deg,#2f6bff,#22d3ee);"></td></tr>
        <tr><td style="padding:28px 32px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                <td style="width:34px;height:34px;border-radius:9px;background:#2f6bff;background-image:linear-gradient(135deg,#2f6bff,#22d3ee);color:#fff;font-weight:bold;font-size:16px;text-align:center;">C</td>
                <td style="padding-left:12px;font-weight:bold;letter-spacing:3px;font-size:15px;color:#ffffff;">CYBER EXAM</td>
            </tr></table>
        </td></tr>
        <tr><td style="padding:16px 32px 32px;font-size:15px;line-height:1.65;color:#c3cddd;">
            <?= $content ?>
        </td></tr>
        <tr><td style="padding:18px 32px;border-top:1px solid #1b2436;font-size:12px;color:#6b7a90;">
            This is an automated message from CYBER EXAM. Please do not reply to this email.<br>
            © <?= date('Y') ?> CYBER EXAM. All rights reserved.
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
