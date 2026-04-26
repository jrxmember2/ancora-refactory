@php
    $brandName = $brand['app_name'] ?? 'Ancora';
    $companyName = $brand['company_name'] ?? ($brand['app_company'] ?? 'Escritorio');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; padding: 24px; font-size: 12px; }
        .header { border-bottom: 3px solid #941415; padding-bottom: 16px; margin-bottom: 20px; }
        .title { font-size: 22px; font-weight: bold; color: #941415; }
        .subtitle { margin-top: 6px; color: #4b5563; }
        .meta { margin-top: 10px; color: #6b7280; font-size: 11px; }
        .summary { margin: 18px 0; padding: 14px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; }
        .summary-item { margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; text-transform: uppercase; font-size: 10px; letter-spacing: 0.08em; }
        .footer { margin-top: 18px; color: #6b7280; font-size: 11px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $title }}</div>
        @if(!empty($subtitle))
            <div class="subtitle">{{ $subtitle }}</div>
        @endif
        <div class="meta">
            Emitido em {{ ($generatedAt ?? now())->format('d/m/Y H:i') }}<br>
            {{ $brandName }} | {{ $companyName }}
        </div>
    </div>

    @if(!empty($summary))
        <div class="summary">
            @foreach($summary as $label => $value)
                <div class="summary-item"><strong>{{ $label }}:</strong> {{ $value }}</div>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($headers as $header)
                        <td>{{ $row[$header] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align:center; color:#6b7280;">Nenhum registro encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Relatorio financeiro gerado pelo modulo Financeiro 360.</div>
</body>
</html>
