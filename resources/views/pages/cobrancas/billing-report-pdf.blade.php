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
    $filterParts[] = 'Periodo: ' . ($periodLabel ?? '-');
    if (($filters['charge_type'] ?? '') !== '') {
        $filterParts[] = 'Tipo: ' . ($filterOptions['chargeTypes'][$filters['charge_type']] ?? $filters['charge_type']);
    }
    if ((int) ($filters['condominium_id'] ?? 0) > 0) {
        $condominiumName = collect($filterOptions['condominiums'] ?? [])->firstWhere('id', (int) $filters['condominium_id'])->name ?? null;
        if ($condominiumName) {
            $filterParts[] = 'Condominio: ' . $condominiumName;
        }
    }
    $snapshotMeta = null;
    if (($snapshotRecord->id ?? null)) {
        $snapshotMeta = 'Snapshot #' . $snapshotRecord->id . ' · ' . (optional($snapshotRecord->generated_at ?? null)->format('d/m/Y H:i') ?: '-');
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatorio de Faturamento de Cobranca</title>
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

        .detail {
            background: #fcfcfd;
        }

        .chip {
            display: inline-block;
            margin: 1px 4px 1px 0;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 2px 6px;
        }

        .paid-entry {
            color: #b91c1c;
            font-weight: 800;
        }

        .paid-generic {
            color: #047857;
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
    </style>
</head>
<body @if($autoPrint ?? false) onload="window.print()" @endif>
    <div class="page">
        <header class="header">
            <img src="{{ $logo }}" alt="Logo" class="logo">
            <div>
                <h1>Relatorio de Faturamento de Cobranca</h1>
                <div class="meta">Emitido em {{ $date }}{{ $filterParts ? ' · ' . implode(' · ', $filterParts) : '' }}</div>
                @if($snapshotMeta)
                    <div class="meta">{{ $snapshotMeta }}</div>
                @endif
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
                <div class="label">Honorarios</div>
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
                            <th class="right">Honorarios</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($block['rows'] as $row)
                            <tr>
                                <td>{{ $row['unit'] }}</td>
                                <td>{{ $row['os_number'] }}@if(($row['case_mode'] ?? 'condominial') === 'avulsa') (Avulsa)@endif</td>
                                <td>{{ $row['debtor'] }}</td>
                                <td>{{ $row['charge_type_label'] }}</td>
                                <td class="right">{{ $money($row['agreement_total']) }}</td>
                                <td class="right">{{ $money($row['paid_amount']) }}<br><span class="muted">{{ $row['paid_label'] }}</span></td>
                                <td class="right">{{ $money($row['projected_amount']) }}</td>
                                <td class="right">{{ $money($row['fees_amount']) }}</td>
                            </tr>
                            <tr class="detail">
                                <td colspan="8">
                                    <div><strong>Cotas cobradas:</strong>
                                        @forelse($row['quota_details'] as $quota)
                                            <span class="chip">{{ $quota['reference'] }} · {{ $quota['due_date'] ?: '-' }} · {{ $quota['amount'] }}</span>
                                        @empty
                                            <span class="muted">Sem cotas vinculadas.</span>
                                        @endforelse
                                    </div>
                                    <div style="margin-top: 4px;"><strong>Parcelamento:</strong>
                                        @forelse($row['payment_plan_details'] as $payment)
                                            <span class="chip">
                                                {{ $payment['label'] }} · {{ $payment['due_date'] ?: '-' }} · {{ $payment['amount'] }}
                                                @if($payment['is_entry'] && $payment['is_paid'])
                                                    <span class="paid-entry">PAGO</span>
                                                @elseif($payment['is_paid'])
                                                    <span class="paid-generic">PAGO</span>
                                                @else
                                                    <span class="muted">{{ $payment['status_label'] }}</span>
                                                @endif
                                            </span>
                                        @empty
                                            <span class="muted">Sem parcelas cadastradas.</span>
                                        @endforelse
                                    </div>
                                </td>
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
                <div><div class="label">Total honorarios</div><div class="value">{{ $money($totals['fees_amount']) }}</div></div>
            </div>
        </section>

        <footer class="footer">
            {{ $brand['company_email'] ?? '' }}{{ ($brand['company_phone'] ?? '') ? ' · ' . $brand['company_phone'] : '' }}{{ ($brand['company_address'] ?? '') ? ' · ' . $brand['company_address'] : '' }}
        </footer>
    </div>
</body>
</html>
