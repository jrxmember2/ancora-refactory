<!DOCTYPE html>
<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subject }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin:0; padding:0; min-width:100%; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="display:none; overflow:hidden; line-height:1px; opacity:0; max-height:0; max-width:0;">
        Solicitação de boleto do acordo da unidade {{ $unitLabel }} - {{ $condominiumName }}.
    </div>

    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#f3f4f6" style="border-collapse:collapse; width:100%; background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <!--[if mso]>
                <table role="presentation" width="720" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                <![endif]-->
                <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="border-collapse:collapse; width:100%; max-width:720px; background-color:#ffffff; border:1px solid #e5e7eb;">
                    <tr>
                        <td bgcolor="#941415" style="padding:24px 28px; background-color:#941415;">
                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; width:100%;">
                                <tr>
                                    <td valign="middle" width="220" style="padding-right:16px;">
                                        @if(!empty($brand['logo_light_url']))
                                            <table role="presentation" border="0" cellspacing="0" cellpadding="0" bgcolor="#ffffff" style="border-collapse:collapse; background-color:#ffffff;">
                                                <tr>
                                                    <td style="padding:10px 14px;">
                                                        <img src="{{ $brand['logo_light_url'] }}" alt="Âncora" width="180" border="0" style="display:block; width:180px; max-width:180px; height:auto; border:0; outline:none; text-decoration:none;">
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                    <td valign="middle" align="right" style="font-family:Arial, Helvetica, sans-serif; color:#fee2e2; text-align:right;">
                                        <p style="margin:0; font-size:11px; line-height:16px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:#fee2e2;">Solicitação de boleto</p>
                                        <p style="margin:8px 0 0 0; font-size:22px; line-height:28px; font-weight:700; color:#ffffff;">{{ $condominiumName }}</p>
                                        <p style="margin:4px 0 0 0; font-size:13px; line-height:18px; color:#fecaca;">Unidade {{ $unitLabel }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; width:100%;">
                                <tr>
                                    <td style="padding:0 0 18px 0; font-size:16px; line-height:24px; font-weight:700; color:#111827; font-family:Arial, Helvetica, sans-serif;">
                                        {{ $greeting }},
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 0 18px 0; font-size:14px; line-height:22px; color:#374151; font-family:Arial, Helvetica, sans-serif;">
                                        Feito acordo conforme os dados abaixo:
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; width:100%; border:1px solid #e5e7eb;">
                                <tr>
                                    <td width="32%" bgcolor="#f9fafb" style="padding:14px 18px; background-color:#f9fafb; font-size:12px; line-height:18px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:1px; font-family:Arial, Helvetica, sans-serif;">
                                        Condomínio
                                    </td>
                                    <td style="padding:14px 18px; font-size:14px; line-height:22px; color:#111827; font-family:Arial, Helvetica, sans-serif;">
                                        {{ $condominiumName }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="32%" bgcolor="#f9fafb" style="padding:14px 18px; background-color:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; line-height:18px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:1px; font-family:Arial, Helvetica, sans-serif;">
                                        Unidade
                                    </td>
                                    <td style="padding:14px 18px; border-top:1px solid #e5e7eb; font-size:14px; line-height:22px; color:#111827; font-family:Arial, Helvetica, sans-serif;">
                                        {{ $unitLabel }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="32%" bgcolor="#f9fafb" style="padding:14px 18px; background-color:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; line-height:18px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:1px; font-family:Arial, Helvetica, sans-serif;">
                                        Condômino(a)
                                    </td>
                                    <td style="padding:14px 18px; border-top:1px solid #e5e7eb; font-size:14px; line-height:22px; color:#111827; font-family:Arial, Helvetica, sans-serif;">
                                        {{ $debtorName }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="32%" bgcolor="#f9fafb" style="padding:14px 18px; background-color:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; line-height:18px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:1px; font-family:Arial, Helvetica, sans-serif;">
                                        Valor total
                                    </td>
                                    <td style="padding:14px 18px; border-top:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif;">
                                        <p style="margin:0; font-size:20px; line-height:26px; font-weight:700; color:#941415;">{{ $agreementTotal }}</p>
                                        <p style="margin:4px 0 0 0; font-size:12px; line-height:18px; color:#6b7280;">{{ $agreementTotalWords }}.</p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; width:100%; margin-top:24px;">
                                <tr>
                                    <td style="padding:0 0 12px 0; font-size:13px; line-height:18px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:1px; font-family:Arial, Helvetica, sans-serif;">
                                        Vencimentos
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; width:100%; border:1px solid #e5e7eb;">
                                @foreach($paymentLines as $line)
                                    <tr>
                                        <td width="140" style="padding:14px 18px; font-size:14px; line-height:20px; color:#111827; font-family:Arial, Helvetica, sans-serif; white-space:nowrap; {{ !$loop->last ? 'border-bottom:1px solid #e5e7eb;' : '' }}">
                                            {{ $line['due_date'] }}
                                        </td>
                                        <td width="16" align="center" style="padding:14px 0; font-size:14px; line-height:20px; font-weight:700; color:#941415; font-family:Arial, Helvetica, sans-serif; white-space:nowrap; {{ !$loop->last ? 'border-bottom:1px solid #e5e7eb;' : '' }}">
                                            -
                                        </td>
                                        <td style="padding:14px 18px 14px 12px; font-family:Arial, Helvetica, sans-serif; {{ !$loop->last ? 'border-bottom:1px solid #e5e7eb;' : '' }}">
                                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; width:100%;">
                                                <tr>
                                                    <td style="padding:0; font-size:14px; line-height:20px; color:#111827; font-family:Arial, Helvetica, sans-serif;">
                                                        @if(!empty($line['display_label']))
                                                            <span style="font-weight:700; color:#941415;">{{ $line['display_label'] }}</span>
                                                            <span style="color:#9ca3af;">&nbsp;-&nbsp;</span>
                                                        @endif
                                                        <span style="color:#111827;">{{ $line['amount'] }}</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>

                            <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse; width:100%; margin-top:24px; border-top:2px solid #941415;">
                                <tr>
                                    <td style="padding-top:18px; font-size:14px; line-height:22px; color:#374151; font-family:Arial, Helvetica, sans-serif;">
                                        Gentileza, fico no aguardo dos boletos.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td bgcolor="#f9fafb" style="padding:18px 28px; background-color:#f9fafb; border-top:1px solid #e5e7eb; font-family:Arial, Helvetica, sans-serif;">
                            <p style="margin:0; font-size:12px; line-height:18px; font-weight:700; color:#374151;">
                                {{ $brand['company_name'] ?? ($brand['app_name'] ?? 'Âncora') }}
                            </p>
                            <p style="margin:4px 0 0 0; font-size:12px; line-height:18px; color:#6b7280;">
                                @if($officeEmail !== ''){{ $officeEmail }}@endif
                                @if($officeEmail !== '' && $officePhone !== '') · @endif
                                @if($officePhone !== ''){{ $officePhone }}@endif
                            </p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                        </td>
                    </tr>
                </table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
