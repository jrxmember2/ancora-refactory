<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório de contratos</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 24px; font-size: 12px; }
        h1 { color: #941415; font-size: 24px; margin: 0 0 8px; }
        .subtitle { color: #6b7280; margin-bottom: 20px; }
        .summary { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
        .card { border: 1px solid #d1d5db; border-radius: 12px; padding: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; vertical-align: top; text-align: left; }
        th { background: #f9fafb; font-size: 11px; text-transform: uppercase; color: #6b7280; }
    </style>
</head>
<body @if(!$pdfMode) onload="window.print()" @endif>
    <h1>Relatório de contratos</h1>
    <div class="subtitle">Extrato consolidado do módulo Contratos.</div>
    <div class="summary">
        <div class="card"><strong>Total de contratos</strong><div>{{ $summary['total'] }}</div></div>
        <div class="card"><strong>Receita prevista</strong><div>R$ {{ number_format((float) $summary['contracted_revenue'], 2, ',', '.') }}</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Título</th>
                <th>Cliente</th>
                <th>Condomínio</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->code ?: '—' }}</td>
                    <td>{{ $item->title }}</td>
                    <td>{{ $item->client?->display_name ?: '—' }}</td>
                    <td>{{ $item->condominium?->name ?: '—' }}</td>
                    <td>{{ $item->type }}</td>
                    <td>{{ $statusLabels[$item->status] ?? $item->status }}</td>
                    <td>R$ {{ number_format((float) ($item->monthly_value ?? $item->contract_value ?? $item->total_value ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
