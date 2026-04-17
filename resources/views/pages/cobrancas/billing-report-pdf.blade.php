@php
    $brand = $ancoraBrand ?? [];
    $pdfMode = $pdfMode ?? false;
    $logoPath = $brand['logo_light'] ?? '/imgs/logomarca.svg';
    if ($pdfMode && !preg_match('#^https?://#i', (string) $logoPath)) {
        $logo = 'file:///' . ltrim(str_replace('\\', '/', public_path(ltrim((string) $logoPath, '/'))), '/');
    } else {
        $logo = $logoPath;
    }

    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $date = now()->format('d/m/Y H:i');
    $filterParts = [];
    if (($filters['billing_status'] ?? '') !== '') {
        $filterParts[] = 'Faturamento: ' . ($filterOptions['billingStatuses'][$filters['billing_status']] ?? $filters['billing_status']);
    }
    if (($filters['charge_type'] ?? '') !== '') {
        $filterParts[] = 'Tipo: ' . ($filterOptions['chargeTypes'][$filters['charge_type']] ?? $filters['charge_type']);
    }
    if (($filters['billing_date_from'] ?? '') !== '') {
        $filterParts[] = 'Faturado de: ' . \Illuminate\Support\Carbon::parse($filters['billing_date_from'])->format('d/m/Y');
    }
    if (($filters['billing_date_to'] ?? '') !== '') {
        $filterParts[] = 'Faturado até: ' . \Illuminate\Support\Carbon::parse($filters['billing_date_to'])->format('d/m/Y');
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de Faturamento de Cobrança</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 16mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9px;
            line-height: 1.35;
        }

        .page {
            min-height: calc(210mm - 32mm);
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border-bottom: 3px solid #941415;
            padding-bottom: 10px;
        }

        .logo {
            max-height: 46px;
            max-width: 180px;
            object-fit: contain;
        }

        h1 {
            margin: 0;
            color: #941415;
            font-size: 18px;
            letter-spacing: 0.04em;
            text-align: right;
            text-transform: uppercase;
        }

        .meta {
            margin-top: 4px;
            color: #6b7280;
            text-align: right;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 8px;
            margin: 14px 0;
        }

        .box {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
        }

        .label {
            color: #6b7280;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .value {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 800;
        }

        h2 {
            margin: 16px 0 6px;
            color: #111827;
            font-size: 12px;
            text-transform: uppercase;
        }

        h3 {
            margin: 10px 0 5px;
            color: #4b5563;
            font-size: 10px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
        }

        th {
            background: #f9fafb;
            color: #4b5563;
            font-size: 8px;
            letter-spacing: 0.08em;
            text-align: left;
            text-transform: uppercase;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 5px 4px;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .muted {
            color: #6b7280;
        }

        .subtotal {
            background: #f9fafb;
            font-weight: 800;
        }

        .grand-total {
            margin-top: 16px;
            border: 2px solid #941415;
            border-radius: 10px;
            padding: 10px;
        }

        .grand-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .footer {
            margin-top: auto;
            border-top: 2px solid #941415;
            padding-top: 8px;
            color: #6b7280;
            font-size: 8px;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body @if($autoPrint ?? false) onload="window.print()" @endif>
    <div class="page">
        <header class="header">
            <img src="{{ $logo }}" alt="Logo" class="logo">
            <div>
                <h1>Relatório de Faturamento de Cobrança</h1>
                <div class="meta">Emitido em {{ $date }}{{ $filterParts ? ' · ' . implode(' · ', $filterParts) : '' }}</div>
            </div>
        </header>

        <section class="summary">
            <div class="box">
                <div class="label">OS</div>
                <div class="value">{{ $totals['cases_count'] }}</div>
            </div>
            <div class="box">
                <div class="label">Acordos</div>
                <div class="value">{{ $money($totals['agreement_total']) }}</div>
            </div>
            <div class="box">
                <div class="label">Recebido</div>
                <div class="value">{{ $money($totals['paid_amount']) }}</div>
            </div>
            <div class="box">
                <div class="label">Projetado</div>
                <div class="value">{{ $money($totals['projected_amount']) }}</div>
            </div>
            <div class="box">
                <div class="label">Honorários</div>
                <div class="value">{{ $money($totals['fees_amount']) }}</div>
            </div>
        </section>

        @forelse($groups as $group)
            <h2>{{ $group['condominium'] }}</h2>
            @foreach($group['blocks'] as $block)
                <h3>{{ $block['block'] }}</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Unidade</th>
                            <th>OS</th>
                            <th>Devedor(a)</th>
                            <th>Tipo</th>
                            <th class="right">Acordo</th>
                            <th class="right">Recebido</th>
                            <th class="right">Projetado</th>
                            <th class="right">Honorários</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($block['rows'] as $row)
                            <tr>
                                <td>{{ $row['unit'] }}</td>
                                <td>{{ $row['os_number'] }}</td>
                                <td>{{ $row['debtor'] }}</td>
                                <td>{{ $row['charge_type_label'] }}</td>
                                <td class="right">{{ $money($row['agreement_total']) }}</td>
                                <td class="right">{{ $money($row['paid_amount']) }}<br><span class="muted">{{ $row['paid_label'] }}</span></td>
                                <td class="right">{{ $money($row['projected_amount']) }}</td>
                                <td class="right">{{ $money($row['fees_amount']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="subtotal">
                            <td colspan="4">Subtotal do bloco</td>
                            <td class="right">{{ $money($block['totals']['agreement_total']) }}</td>
                            <td class="right">{{ $money($block['totals']['paid_amount']) }}</td>
                            <td class="right">{{ $money($block['totals']['projected_amount']) }}</td>
                            <td class="right">{{ $money($block['totals']['fees_amount']) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endforeach
        @empty
            <p>Nenhuma OS encontrada para os filtros selecionados.</p>
        @endforelse

        <section class="grand-total">
            <div class="grand-grid">
                <div><div class="label">Total dos acordos</div><div class="value">{{ $money($totals['agreement_total']) }}</div></div>
                <div><div class="label">Total recebido</div><div class="value">{{ $money($totals['paid_amount']) }}</div></div>
                <div><div class="label">Total projetado</div><div class="value">{{ $money($totals['projected_amount']) }}</div></div>
                <div><div class="label">Total honorários</div><div class="value">{{ $money($totals['fees_amount']) }}</div></div>
            </div>
        </section>

        <footer class="footer">
            {{ $brand['company_email'] ?? '' }}{{ ($brand['company_phone'] ?? '') ? ' · ' . $brand['company_phone'] : '' }}{{ ($brand['company_address'] ?? '') ? ' · ' . $brand['company_address'] : '' }}
        </footer>
    </div>
</body>
</html>
