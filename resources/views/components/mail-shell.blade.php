@props(['title', 'preheader'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-shell { width: 100% !important; }
            .email-padding { padding-left: 18px !important; padding-right: 18px !important; }
            .stack-cell { display: block !important; width: 100% !important; padding-right: 0 !important; padding-left: 0 !important; padding-bottom: 14px !important; }
        }
    </style>
</head>
<body data-visual-system="incline" style="margin:0; padding:0; background:#F8F8F8; color:#171717; font-family:Arial, Helvetica, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preheader }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F8F8F8;">
        <tr>
            <td align="center" style="padding:30px 12px;">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" class="email-shell" style="width:620px; max-width:620px;">
                    <tr>
                        <td class="email-padding" style="padding:0; background:#F41E1E;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding:27px 30px; background:#1D2229; color:#FFFFFF;">
                                        <div style="color:#FFFFFF; font-size:12px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase;">Incline Fitness</div>
                                        <h1 style="margin:12px 0 7px; font-size:28px; line-height:1.15; color:#FFFFFF;">{{ $title }}</h1>
                                        <div style="color:#D8DDE1; font-size:15px; line-height:1.5;">{{ $preheader }}</div>
                                    </td>
                                </tr>
                                <tr><td style="height:6px; background:#F41E1E; font-size:0; line-height:0;">&nbsp;</td></tr>
                            </table>
                        </td>
                    </tr>
                    <tr><td style="height:16px;"></td></tr>
                    {{ $slot }}
                    <tr>
                        <td style="padding:22px 18px 6px; color:#6A6A6A; font-size:11px; line-height:1.6; text-align:center;">
                            This is an automated email from {{ config('app.name') }}.<br>
                            Please keep this message for your records.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
