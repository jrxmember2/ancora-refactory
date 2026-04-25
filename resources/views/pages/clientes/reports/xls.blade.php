<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111827;
        }

        h1 {
            margin: 0 0 4px;
            color: #941415;
            font-size: 22px;
        }

        .subtitle {
            margin: 0 0 12px;
            color: #6b7280;
        }

        .meta,
        .filters {
            margin: 0 0 10px;
            color: #374151;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 16px;
        }

        .summary td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            vertical-align: top;
        }

        .summary .label {
            display: block;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary .value {
            display: block;
            margin-top: 4px;
            font-size: 15px;
            font-weight: 700;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th,
        table.report td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            vertical-align: top;
            text-align: left;
        }

        table.report th {
            background: #f3f4f6;
            color: #374151;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        table.report td {
            white-space: pre-line;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="subtitle">{{ $subtitle }}</p>
    <p class="meta">Emitido em {{ $generatedAt->format('d/m/Y H:i') }}</p>
    @if(!empty($filtersApplied))
        <p class="filters">Filtros aplicados: {{ implode(' · ', $filtersApplied) }}</p>
    @endif

    @if(!empty($summary))
        <table class="summary">
            <tr>
                @foreach($summary as $item)
                    <td>
                        <span class="label">{{ $item['label'] }}</span>
                        <span class="value">{{ $item['value'] }}</span>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="report">
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
                        <td>{{ $row[$header] ?? '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">Nenhum registro encontrado para os filtros selecionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
