@php
    $brand = $ancoraBrand ?? [];
    $logoPath = $brand['logo_light'] ?? '/imgs/logomarca.svg';

    if (($pdfMode ?? false) && !preg_match('#^https?://#i', (string) $logoPath)) {
        $logo = 'file:///' . ltrim(str_replace('\\', '/', public_path(ltrim((string) $logoPath, '/'))), '/');
    } else {
        $logo = $logoPath;
    }
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4;
            margin: 14mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.45;
        }

        .page {
            min-height: calc(297mm - 28mm);
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
            max-width: 170px;
            max-height: 52px;
            object-fit: contain;
        }

        .header-title {
            text-align: right;
        }

        h1 {
            margin: 0;
            color: #941415;
            font-size: 18px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .subtitle {
            margin-top: 4px;
            color: #6b7280;
            font-size: 10px;
        }

        .meta {
            margin-top: 6px;
            color: #6b7280;
            font-size: 9px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin: 14px 0;
        }

        .summary-box {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
        }

        .summary-label {
            color: #6b7280;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .summary-value {
            margin-top: 4px;
            color: #111827;
            font-size: 13px;
            font-weight: 800;
        }

        .filters {
            margin-bottom: 10px;
            color: #4b5563;
            font-size: 9px;
        }

        .record {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            margin-bottom: 12px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .record-head {
            padding: 12px 14px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .record-title {
            margin: 0;
            color: #111827;
            font-size: 13px;
            font-weight: 800;
        }

        .record-subtitle {
            margin-top: 4px;
            color: #6b7280;
            font-size: 9px;
        }

        .badges {
            margin-top: 8px;
        }

        .badge {
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 4px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .record-body {
            padding: 12px 14px;
        }

        .section {
            margin-bottom: 12px;
        }

        .section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            margin: 0 0 6px;
            color: #941415;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        table.fields {
            width: 100%;
            border-collapse: collapse;
        }

        table.fields td {
            border-bottom: 1px solid #f1f5f9;
            padding: 5px 0;
            vertical-align: top;
        }

        td.field-label {
            width: 26%;
            padding-right: 10px;
            color: #6b7280;
            font-weight: 700;
        }

        td.field-value {
            white-space: pre-line;
        }

        ul.lines {
            margin: 0;
            padding-left: 16px;
        }

        ul.lines li {
            margin: 0 0 4px;
        }

        .record-footer {
            padding: 10px 14px 12px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 8px;
        }

        .empty {
            border: 1px dashed #d1d5db;
            border-radius: 12px;
            padding: 18px;
            color: #6b7280;
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
<body>
    <div class="page">
        <header class="header">
            <img src="{{ $logo }}" alt="Logo" class="logo">
            <div class="header-title">
                <h1>{{ $title }}</h1>
                <div class="subtitle">{{ $subtitle }}</div>
                <div class="meta">Emitido em {{ $generatedAt->format('d/m/Y H:i') }}</div>
            </div>
        </header>

        @if(!empty($summary))
            <section class="summary">
                @foreach($summary as $item)
                    <div class="summary-box">
                        <div class="summary-label">{{ $item['label'] }}</div>
                        <div class="summary-value">{{ $item['value'] }}</div>
                    </div>
                @endforeach
            </section>
        @endif

        @if(!empty($filtersApplied))
            <div class="filters">Filtros aplicados: {{ implode(' · ', $filtersApplied) }}</div>
        @endif

        @forelse($records as $record)
            <article class="record">
                <div class="record-head">
                    <h2 class="record-title">{{ $record['title'] }}</h2>
                    @if(!empty($record['subtitle']))
                        <div class="record-subtitle">{{ $record['subtitle'] }}</div>
                    @endif
                    @if(!empty($record['badges']))
                        <div class="badges">
                            @foreach($record['badges'] as $badge)
                                <span class="badge">{{ $badge }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="record-body">
                    @foreach($record['sections'] as $section)
                        @php
                            $fields = collect($section['fields'] ?? [])->filter(fn ($field) => trim((string) ($field['value'] ?? '')) !== '');
                            $lines = collect($section['lines'] ?? [])->filter(fn ($line) => trim((string) $line) !== '');
                        @endphp
                        @if($fields->isEmpty() && $lines->isEmpty())
                            @continue
                        @endif
                        <section class="section">
                            <h3 class="section-title">{{ $section['title'] }}</h3>
                            @if($fields->isNotEmpty())
                                <table class="fields">
                                    <tbody>
                                        @foreach($fields as $field)
                                            <tr>
                                                <td class="field-label">{{ $field['label'] }}</td>
                                                <td class="field-value">{{ $field['value'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                            @if($lines->isNotEmpty())
                                <ul class="lines">
                                    @foreach($lines as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endforeach
                </div>

                @if(!empty($record['footer']))
                    <div class="record-footer">{{ $record['footer'] }}</div>
                @endif
            </article>
        @empty
            <div class="empty">Nenhum registro encontrado para os filtros selecionados.</div>
        @endforelse

        <footer class="footer">
            {{ $brand['company_email'] ?? '' }}{{ ($brand['company_phone'] ?? '') ? ' · ' . $brand['company_phone'] : '' }}{{ ($brand['company_address'] ?? '') ? ' · ' . $brand['company_address'] : '' }}
        </footer>
    </div>
</body>
</html>
