<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; color:#1f2937; font-family:Arial, Helvetica, sans-serif;">
    <div style="padding:24px 12px;">
        <div style="max-width:720px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:24px; overflow:hidden;">
            <div style="background:linear-gradient(135deg, #941415 0%, #b91c1c 100%); padding:24px 28px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="vertical-align:middle;">
                            @if(!empty($brand['logo_light_url']))
                                <img src="{{ $brand['logo_light_url'] }}" alt="Âncora" style="max-width:180px; max-height:56px; display:block; background:#ffffff; border-radius:16px; padding:10px;">
                            @endif
                        </td>
                        <td style="vertical-align:middle; text-align:right; color:#fee2e2;">
                            <div style="font-size:11px; letter-spacing:0.14em; text-transform:uppercase; font-weight:700;">Solicitação de boleto</div>
                            <div style="margin-top:8px; font-size:20px; font-weight:700; color:#ffffff;">{{ $condominiumName }}</div>
                            <div style="margin-top:4px; font-size:13px; color:#fecaca;">Unidade {{ $unitLabel }}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="padding:28px;">
                <p style="margin:0 0 18px; font-size:16px; font-weight:600;">{{ $greeting }},</p>
                <p style="margin:0 0 18px; font-size:14px; line-height:1.7;">Feito acordo conforme os dados abaixo:</p>

                <div style="border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td style="width:32%; padding:14px 18px; background:#f9fafb; font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.08em;">Condomínio</td>
                            <td style="padding:14px 18px; font-size:14px; color:#111827;">{{ $condominiumName }}</td>
                        </tr>
                        <tr>
                            <td style="width:32%; padding:14px 18px; background:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.08em;">Unidade</td>
                            <td style="padding:14px 18px; border-top:1px solid #e5e7eb; font-size:14px; color:#111827;">{{ $unitLabel }}</td>
                        </tr>
                        <tr>
                            <td style="width:32%; padding:14px 18px; background:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.08em;">Condômino(a)</td>
                            <td style="padding:14px 18px; border-top:1px solid #e5e7eb; font-size:14px; color:#111827;">{{ $debtorName }}</td>
                        </tr>
                        <tr>
                            <td style="width:32%; padding:14px 18px; background:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.08em;">Valor total</td>
                            <td style="padding:14px 18px; border-top:1px solid #e5e7eb; font-size:14px; color:#111827;">
                                <div style="font-size:18px; font-weight:700; color:#941415;">{{ $agreementTotal }}</div>
                                <div style="margin-top:4px; font-size:12px; color:#6b7280;">{{ $agreementTotalWords }}.</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top:24px;">
                    <div style="font-size:13px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.08em;">Vencimentos</div>
                    <div style="margin-top:12px; border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
                        @foreach($paymentLines as $line)
                            <div style="display:flex; justify-content:space-between; gap:16px; padding:14px 18px; {{ !$loop->last ? 'border-bottom:1px solid #e5e7eb;' : '' }}">
                                <div style="font-size:14px; color:#111827;">
                                    {{ $line['due_date'] }}
                                    @if(!empty($line['display_label']))
                                        <span style="font-weight:700; color:#941415;"> - {{ $line['display_label'] }}</span>
                                    @endif
                                </div>
                                <div style="font-size:14px; font-weight:700; color:#111827;">{{ $line['amount'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div style="margin-top:24px; border-top:2px solid #941415; padding-top:18px;">
                    <p style="margin:0; font-size:14px; line-height:1.7;">Gentileza, fico no aguardo dos boletos.</p>
                </div>
            </div>

            <div style="padding:18px 28px; background:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; color:#6b7280;">
                <div style="font-weight:700; color:#374151;">{{ $brand['company_name'] ?? ($brand['app_name'] ?? 'Âncora') }}</div>
                <div style="margin-top:4px;">
                    @if($officeEmail !== ''){{ $officeEmail }}@endif
                    @if($officeEmail !== '' && $officePhone !== '') · @endif
                    @if($officePhone !== ''){{ $officePhone }}@endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
