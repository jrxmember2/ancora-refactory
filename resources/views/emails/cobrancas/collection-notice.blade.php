<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:24px; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px; background:#ffffff; border-radius:20px; overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px; background:#111827; color:#ffffff;">
                            <div style="font-size:18px; font-weight:700;">{{ $subject }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px; font-size:14px; line-height:1.7; color:#374151;">{!! nl2br(e($bodyText)) !!}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
