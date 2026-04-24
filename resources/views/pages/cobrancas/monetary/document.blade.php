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
    $percent = fn ($value, $decimals = 2) => number_format((float) $value, $decimals, ',', '.') . '%';
    $date = fn ($value) => $value ? optional($value)->format('d/m/Y') : '-';
    $payloadTotals = $update->payload_json['totals_cents'] ?? [];
    $costsCorrectedAmount = (float) ($update->costs_corrected_amount ?? (((int) ($payloadTotals['costs_corrected_cents'] ?? 0)) / 100));
    $boletoFeeTotal = (float) ($update->boleto_fee_total ?? (((int) ($payloadTotals['boleto_fee_cents'] ?? 0)) / 100));
    $boletoCancellationFeeTotal = (float) ($update->boleto_cancellation_fee_total ?? (((int) ($payloadTotals['boleto_cancellation_fee_cents'] ?? 0)) / 100));
    $grandTotalInWords = \App\Support\BrazilianCurrencyFormatter::toWords((float) $update->grand_total);
    $attorneyFeeLabel = match ($update->attorney_fee_type) {
        'fixed' => 'Honorários advocatícios fixos',
        'none' => 'Sem honorários advocatícios',
        default => 'Honorários advocatícios de ' . $percent($update->attorney_fee_value) . ' sobre o débito atualizado',
    };
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Memória de Cálculo TJES - {{ $case->os_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 18mm 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
        }

        .page {
            min-height: calc(297mm - 36mm);
            display: flex;
            flex-direction: column;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            border-bottom: 3px solid #941415;
            padding-bottom: 12px;
        }

        .logo {
            max-height: 52px;
            max-width: 190px;
            object-fit: contain;
        }

        .title-block {
            text-align: right;
        }

        h1 {
            margin: 0;
            color: #941415;
            font-size: 18px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        h2 {
            margin: 18px 0 8px;
            color: #111827;
            font-size: 13px;
            text-transform: uppercase;
        }

        .muted {
            color: #6b7280;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px 18px;
        }

        .box {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
        }

        .label {
            color: #6b7280;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .value {
            margin-top: 3px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            color: #4b5563;
            font-size: 9px;
            letter-spacing: 0.08em;
            text-align: left;
            text-transform: uppercase;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 6px;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .totals {
            margin-top: 16px;
            margin-left: auto;
            width: 330px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 0;
        }

        .grand {
            color: #941415;
            font-size: 15px;
            font-weight: 800;
        }

        .notes {
            margin-top: 18px;
            border-top: 2px solid #941415;
            padding-top: 10px;
            color: #4b5563;
            font-size: 10px;
        }

        .footer {
            margin-top: auto;
            border-top: 2px solid #941415;
            padding-top: 8px;
            color: #6b7280;
            font-size: 9px;
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
            <div class="title-block">
                <h1>Memória de Cálculo TJES</h1>
                <div class="muted">OS {{ $case->os_number }} · emitida em {{ now()->format('d/m/Y H:i') }}</div>
            </div>
        </header>

        <h2>Dados da cobrança</h2>
        <section class="grid">
            <div class="box">
                <div class="label">Condomínio</div>
                <div class="value">{{ $case->condominium?->name ?? '-' }}</div>
            </div>
            <div class="box">
                <div class="label">Unidade</div>
                <div class="value">{{ $case->block?->name ? $case->block->name . ' · ' : '' }}{{ $case->unit?->unit_number ?? '-' }}</div>
            </div>
            <div class="box">
                <div class="label">Devedor(a)</div>
                <div class="value">{{ $case->debtor_name_snapshot ?: '-' }}</div>
            </div>
            <div class="box">
                <div class="label">Documento</div>
                <div class="value">{{ $case->debtor_document_snapshot ?: '-' }}</div>
            </div>
            <div class="box">
                <div class="label">Índice / data final</div>
                <div class="value">{{ $update->index_code === 'ATM' ? 'Índice do TJES' : $update->index_code }} · {{ $date($update->final_date) }}</div>
            </div>
            <div class="box">
                <div class="label">Juros / multa / honorários</div>
                <div class="value">
                    {{ $update->interest_type === 'legal' ? 'Juros legais' : ($update->interest_type === 'contractual' ? 'Juros contratuais de ' . $percent($update->interest_rate_monthly) . ' a.m.' : 'Sem juros') }}
                    · Multa {{ $percent($update->fine_percent) }}
                    · {{ $attorneyFeeLabel }}
                </div>
            </div>
        </section>

        <h2>Quotas atualizadas</h2>
        <table>
            <thead>
                <tr>
                    <th>Referência</th>
                    <th>Vencimento</th>
                    <th class="right">Original</th>
                    <th class="right">Fator TJES</th>
                    <th class="right">Corrigido</th>
                    <th class="right">Juros</th>
                    <th class="right">Multa</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($update->items as $item)
                    <tr>
                        <td>{{ $item->reference_label ?: optional($item->due_date)->format('m/Y') }}</td>
                        <td>{{ $date($item->due_date) }}</td>
                        <td class="right">{{ $money($item->original_amount) }}</td>
                        <td class="right">{{ number_format((float) $item->correction_factor, 10, ',', '.') }}</td>
                        <td class="right">{{ $money($item->corrected_amount) }}</td>
                        <td class="right">{{ $money($item->interest_amount) }}<br><span class="muted">{{ $percent($item->interest_percent) }}</span></td>
                        <td class="right">{{ $money($item->fine_amount) }}</td>
                        <td class="right"><strong>{{ $money($item->total_amount) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="totals">
            <div class="total-row"><span>Valor original</span><strong>{{ $money($update->original_total) }}</strong></div>
            <div class="total-row"><span>Principal corrigido</span><strong>{{ $money($update->corrected_total) }}</strong></div>
            <div class="total-row"><span>Juros moratórios</span><strong>{{ $money($update->interest_total) }}</strong></div>
            <div class="total-row"><span>Multa</span><strong>{{ $money($update->fine_total) }}</strong></div>
            @if($costsCorrectedAmount > 0)
                <div class="total-row"><span>Custas/despesas corrigidas</span><strong>{{ $money($costsCorrectedAmount) }}</strong></div>
            @endif
            @if($boletoFeeTotal > 0)
                <div class="total-row"><span>Taxa de boleto</span><strong>{{ $money($boletoFeeTotal) }}</strong></div>
            @endif
            @if($boletoCancellationFeeTotal > 0)
                <div class="total-row"><span>Taxa de cancelamento de boleto</span><strong>{{ $money($boletoCancellationFeeTotal) }}</strong></div>
            @endif
            @if((float) $update->abatement_amount > 0)
                <div class="total-row"><span>Abatimento</span><strong>- {{ $money($update->abatement_amount) }}</strong></div>
            @endif
            <div class="total-row"><span>Total 1 - débito atualizado</span><strong>{{ $money($update->debit_total) }}</strong></div>
            <div class="total-row"><span>Total 2 - honorários</span><strong>{{ $money($update->attorney_fee_amount) }}</strong></div>
            <div class="total-row grand"><span>Total geral</span><span>{{ $money($update->grand_total) }}</span></div>
            <div class="muted" style="padding-top: 6px; text-align: right;">{{ ucfirst($grandTotalInWords) }}.</div>
        </section>

        <section class="notes">
            <strong>Notas:</strong> fator de correção aplicado com base na tabela de Atualização Monetária de Débitos Judiciais do Poder Judiciário do Estado do Espírito Santo. Juros legais calculados em base mensal proporcional de 30 dias, observando 0,5% ao mês até 10/01/2003 e 1% ao mês a partir de 11/01/2003.
        </section>

        <footer class="footer">
            Memória vinculada à OS {{ $case->os_number }} · cálculo #{{ $update->id }} · {{ $brand['company_email'] ?? '' }} {{ ($brand['company_phone'] ?? '') ? '· ' . $brand['company_phone'] : '' }}
        </footer>
    </div>
</body>
</html>
