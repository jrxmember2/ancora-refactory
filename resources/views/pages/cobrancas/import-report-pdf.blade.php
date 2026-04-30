<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatorio de Importacao de Inadimplencia</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 24px 0 8px; }
        p { margin: 0 0 6px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: top; text-align: left; }
        th { background: #f9fafb; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #6b7280; }
        .grid { display: table; width: 100%; margin-top: 12px; }
        .card { display: table-cell; width: 20%; border: 1px solid #e5e7eb; padding: 12px; margin-right: 8px; }
        .label { font-size: 10px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 18px; font-weight: bold; color: #111827; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>Relatorio de Importacao de Inadimplencia</h1>
    <p>Lote #{{ $batch->id }} · {{ $batch->original_name }}</p>
    <p>Status: {{ $batch->status }} · Gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <div class="grid">
        <div class="card">
            <div class="label">Linhas lidas</div>
            <div class="value">{{ $summary['total_rows'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Prontas</div>
            <div class="value">{{ $summary['ready_rows'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Bloqueios</div>
            <div class="value">{{ $summary['blocking_rows'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Ignoradas</div>
            <div class="value">{{ $summary['ignored_rows'] ?? 0 }}</div>
        </div>
        <div class="card">
            <div class="label">Processadas</div>
            <div class="value">{{ $summary['processed_rows'] ?? 0 }}</div>
        </div>
    </div>

    <h2>Linhas</h2>
    <table>
        <thead>
            <tr>
                @foreach(array_keys($rows[0] ?? ['Linha' => '', 'Status' => '', 'Condominio' => '', 'Unidade' => '', 'Referencia' => '', 'Vencimento' => '', 'Valor' => '', 'OS' => '', 'Detalhe' => '']) as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="12">Nenhuma linha encontrada para este lote.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
