@php
    $brandName = $brand['app_name'] ?? 'Ancora';
    $companyName = $brand['company_name'] ?? ($brand['app_company'] ?? 'Escritorio');
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Prestacao de contas</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; padding: 24px; font-size: 12px; }
        h1 { color: #941415; margin: 0; font-size: 22px; }
        .meta { color: #6b7280; margin-top: 8px; }
        .summary { margin-top: 18px; padding: 14px; background: #f9fafb; border: 1px solid #e5e7eb; }
        .summary-grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .summary-grid td { border: 1px solid #d1d5db; padding: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; text-transform: uppercase; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Prestacao de contas</h1>
    <div class="meta">
        Periodo: {{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }}<br>
        Emitido em {{ now()->format('d/m/Y H:i') }} · {{ $brandName }} · {{ $companyName }}
    </div>

    <div class="summary">
        <strong>Resumo financeiro</strong>
        <table class="summary-grid">
            <tr><td>Entradas</td><td>{{ $money($data['summary']['entradas']) }}</td></tr>
            <tr><td>Honorarios</td><td>{{ $money($data['summary']['honorarios']) }}</td></tr>
            <tr><td>Custas</td><td>{{ $money($data['summary']['custas']) }}</td></tr>
            <tr><td>Repasses</td><td>{{ $money($data['summary']['repasses']) }}</td></tr>
            <tr><td>Saldo</td><td>{{ $money($data['summary']['saldo']) }}</td></tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Documento</th>
                <th>Descricao</th>
                <th>Data</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['receivables'] as $item)
                <tr>
                    <td>Entrada</td>
                    <td>{{ $item->code }}</td>
                    <td>{{ $item->title }}</td>
                    <td>{{ optional($item->received_at ?: $item->due_date)->format('d/m/Y') }}</td>
                    <td>{{ $money($item->received_amount) }}</td>
                </tr>
            @endforeach
            @foreach($data['costs'] as $item)
                <tr>
                    <td>Custa</td>
                    <td>{{ $item->code }}</td>
                    <td>{{ $item->cost_type ?: 'Custa processual' }}</td>
                    <td>{{ optional($item->cost_date)->format('d/m/Y') }}</td>
                    <td>{{ $money($item->amount) }}</td>
                </tr>
            @endforeach
            @foreach($data['repasses'] as $item)
                <tr>
                    <td>Repasse</td>
                    <td>{{ $item->code }}</td>
                    <td>{{ $item->description ?: 'Repasse financeiro' }}</td>
                    <td>{{ optional($item->transaction_date)->format('d/m/Y') }}</td>
                    <td>{{ $money($item->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
