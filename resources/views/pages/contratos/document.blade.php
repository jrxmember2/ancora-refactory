<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->title }}</title>
    @php
        $margins = array_merge(['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3], $contract->template?->margins_json ?? []);
    @endphp
    <style>
        @page {
            size: A4 {{ ($contract->template?->page_orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait' }};
            margin: {{ (float) ($margins['top'] ?? 3) }}cm {{ (float) ($margins['right'] ?? 2) }}cm {{ (float) ($margins['bottom'] ?? 2) }}cm {{ (float) ($margins['left'] ?? 3) }}cm;
        }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 13px; line-height: 1.6; margin: 0; }
        .wrapper { width: 100%; }
        .header { border-bottom: 3px solid #941415; padding-bottom: 14px; margin-bottom: 22px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-title { text-align: right; }
        .title { font-size: 22px; font-weight: 700; color: #941415; text-transform: uppercase; }
        .subtitle { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.12em; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .meta td { border: 1px solid #d1d5db; padding: 10px; vertical-align: top; }
        .meta strong { display: block; margin-bottom: 4px; font-size: 11px; color: #6b7280; text-transform: uppercase; }
        .body { min-height: 380px; }
        .body table { width: 100%; border-collapse: collapse; }
        .body table td, .body table th { border: 1px solid #d1d5db; padding: 8px; }
        .closing { margin-top: 24px; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 34px; }
        .signatures td { width: 50%; padding-top: 34px; vertical-align: top; }
        .line { border-top: 1px solid #111827; padding-top: 8px; font-size: 12px; font-weight: 700; text-align: center; }
        .footer { margin-top: 28px; border-top: 2px solid #941415; padding-top: 10px; font-size: 11px; color: #6b7280; text-align: center; }
    </style>
</head>
<body @if(!empty($autoPrint)) onload="window.print()" @endif>
    <div class="wrapper">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td>
                        @if(($settings['show_logo'] ?? false) && !empty($brand['logo_light']))
                            <img src="{{ asset(ltrim($brand['logo_light'], '/')) }}" alt="Logo" style="max-height: 70px;">
                        @endif
                    </td>
                    <td class="header-title">
                        <div class="subtitle">{{ $contract->type }}</div>
                        <div class="title">{{ $contract->title }}</div>
                        <div style="font-size:12px; color:#6b7280; margin-top:4px;">{{ $contract->code ?: 'Sem código' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        @if(!empty($contract->template?->header_html))
            <div>{!! $contract->template->header_html !!}</div>
        @endif

        <table class="meta">
            <tr>
                <td>
                    <strong>Cliente</strong>
                    {{ $client_label ?: 'Não informado' }}
                </td>
                <td>
                    <strong>Condomínio</strong>
                    {{ $condominium_label ?: 'Não aplicável' }}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>Unidade</strong>
                    {{ $unit_label ?: 'Não aplicável' }}
                </td>
                <td>
                    <strong>Valor</strong>
                    {{ $variables['contrato_valor'] ?: 'Não informado' }}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>Início</strong>
                    {{ $variables['contrato_data_inicio'] ?: 'Não informado' }}
                </td>
                <td>
                    <strong>Término</strong>
                    {{ $variables['contrato_data_fim'] ?: 'Não informado' }}
                </td>
            </tr>
        </table>

        <div class="body">{!! $content_html !!}</div>

        <div class="closing">
            <p>{{ ucfirst($location_label ?: 'vitória/es') }}, {{ $date_long }}.</p>
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="line">{{ $client_label ?: 'Contratante' }}</div>
                </td>
                <td>
                    <div class="line">{{ $brand['company_name'] ?? 'Contratada' }}</div>
                </td>
            </tr>
        </table>

        @if(!empty($contract->template?->footer_html))
            <div style="margin-top: 20px;">{!! $contract->template->footer_html !!}</div>
        @endif

        <div class="footer">{{ $settings['footer_text'] ?? '' }}</div>
    </div>
</body>
</html>
