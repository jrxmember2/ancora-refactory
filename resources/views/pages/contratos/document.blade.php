<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->title }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @php
        $margins = array_merge(['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3], $contract->template?->margins_json ?? []);
        $pageSize = match ($contract->template?->page_size ?? 'a4') {
            'legal' => 'Legal',
            'letter' => 'Letter',
            default => 'A4',
        };
        $orientation = ($contract->template?->page_orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
        $appendixSections = collect($appendixSections ?? [])->filter(fn ($item) => !empty($item['pages']))->values();
        $displayTitle = trim((string) ($rendered_title ?? '')) !== '';
        $contractingParty = $parties['contracting'] ?? [];
        $contractedParty = $parties['contracted'] ?? [];
        $hasCustomFooter = trim((string) ($rendered_footer_html ?? '')) !== '';
        $renderFooterInBody = $renderFooterInBody ?? true;
        $footerMeasureSource = $hasCustomFooter ? (string) $rendered_footer_html : (string) ($settings['footer_text'] ?? '');
        $footerMeasureText = trim((string) preg_replace('/\s+/u', ' ', strip_tags(str_ireplace(
            ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</tr>'],
            ["\n", "\n", "\n", "\n", "\n", "\n", "\n"],
            $footerMeasureSource
        ))));
        $footerLineHints = substr_count(mb_strtolower($footerMeasureSource, 'UTF-8'), '<br')
            + substr_count(mb_strtolower($footerMeasureSource, 'UTF-8'), '<tr')
            + substr_count(mb_strtolower($footerMeasureSource, 'UTF-8'), '<p')
            + substr_count(mb_strtolower($footerMeasureSource, 'UTF-8'), '<div')
            + substr_count(mb_strtolower($footerMeasureSource, 'UTF-8'), '</li>');
        $footerLengthHints = max(0, (int) ceil(max(0, mb_strlen($footerMeasureText, 'UTF-8') - 90) / 90));
        $footerVisualLines = max(1, min(8, $footerLineHints > 0 ? $footerLineHints : 1));
        $footerReserveCm = $renderFooterInBody ? min(5.8, 2.0 + (($footerVisualLines - 1) * 0.45) + ($footerLengthHints * 0.3)) : 0;
        $effectiveBottomMargin = (float) ($margins['bottom'] ?? 2) + $footerReserveCm;
        $footerBottomOffsetCm = 0.1;
    @endphp
    <style>
        @page {
            size: {{ $pageSize }} {{ $orientation }};
            margin: {{ (float) ($margins['top'] ?? 3) }}cm {{ (float) ($margins['right'] ?? 2) }}cm {{ $effectiveBottomMargin }}cm {{ (float) ($margins['left'] ?? 3) }}cm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
            font-size: 13px;
            line-height: 1.65;
            margin: 0;
        }
        .wrapper {
            width: 100%;
            padding-bottom: 0;
        }
        .default-header {
            border-bottom: 3px solid #941415;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .default-header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .default-header-title {
            text-align: right;
        }
        .default-title {
            font-size: 24px;
            font-weight: 700;
            color: #941415;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .default-code {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
        }
        .custom-fragment {
            margin-bottom: 24px;
        }
        .custom-fragment table,
        .contract-body table,
        .custom-page-footer table {
            width: 100%;
            border-collapse: collapse;
        }
        .custom-fragment td,
        .custom-fragment th,
        .contract-body td,
        .contract-body th,
        .custom-page-footer td,
        .custom-page-footer th {
            border: 1px solid #d1d5db;
            padding: 8px;
        }
        .party-shell {
            margin-bottom: 24px;
        }
        .party-card {
            border: 1px solid #941415;
            border-radius: 18px;
            background: #f5f5f6;
            padding: 16px 18px;
        }
        .party-card + .party-card {
            margin-top: 14px;
        }
        .party-title {
            margin-bottom: 12px;
            font-size: 11px;
            font-weight: 700;
            color: #a73738;
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }
        .party-lines {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            table-layout: fixed;
        }
        .party-lines td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .party-pair {
            width: 50%;
            padding-right: 12px;
        }
        .party-lines tr > td + td {
            padding-left: 14px;
        }
        .party-field {
            width: 100%;
            border-collapse: collapse;
        }
        .party-field td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .party-label {
            width: 92px;
            font-weight: 700;
            color: #374151;
            white-space: nowrap;
            padding-right: 10px;
        }
        .party-field td:last-child {
            color: #111827;
            font-weight: 500;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        .contract-body {
            min-height: 380px;
        }
        .contract-body img,
        .custom-fragment img,
        .custom-page-footer img {
            max-width: 100%;
            height: auto;
        }
        .page-break {
            break-before: page;
            page-break-before: always;
        }
        .appendix-shell {
            margin-top: 0;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .appendix-title {
            font-size: 15px;
            font-weight: 700;
            color: #941415;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .appendix-subtitle {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
        }
        .appendix-owner {
            margin-top: 2px;
            font-size: 11px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .appendix-image-wrap {
            margin-top: 18px;
            text-align: center;
        }
        .appendix-image {
            max-width: 100%;
            max-height: 21cm;
            object-fit: contain;
        }
        .page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: {{ $footerBottomOffsetCm }}cm;
            font-size: 10px;
            line-height: 1.35;
            color: #6b7280;
        }
        .default-page-footer {
            border-top: 2px solid #941415;
            padding-top: 8px;
            text-align: center;
            text-transform: lowercase;
        }
        .custom-page-footer {
            padding-top: 6px;
        }
        .ancora-page-number::before {
            content: {{ $renderFooterInBody ? "''" : 'counter(page)' }};
        }
        .ancora-page-total::before {
            content: {{ $renderFooterInBody ? "''" : 'counter(pages)' }};
        }
    </style>
</head>
<body @if(!empty($autoPrint)) onload="window.print()" @endif>
    <div class="wrapper">
        @if(!empty($rendered_header_html))
            <div class="custom-fragment">{!! $rendered_header_html !!}</div>
        @else
            <div class="default-header">
                <table class="default-header-table">
                    <tr>
                        <td>
                            @if(($settings['show_logo'] ?? false) && !empty($brand['logo_light']))
                                <img src="{{ asset(ltrim($brand['logo_light'], '/')) }}" alt="Logo" style="max-height: 70px;">
                            @endif
                        </td>
                        <td class="default-header-title">
                            @if($displayTitle)
                                <div class="default-title">{{ $rendered_title }}</div>
                            @endif
                            <div class="default-code">{{ $contract->code ?: 'Sem codigo' }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        @if(!empty($rendered_qualification_html))
            <div class="custom-fragment">{!! $rendered_qualification_html !!}</div>
        @elseif(false && (!empty($contractingParty['rows']) || !empty($contractedParty['rows'])))
            <div class="party-shell">
                @foreach([$contractingParty, $contractedParty] as $party)
                    <div class="party-card">
                        @if(!empty($party['title']))
                            <div class="party-title">{{ $party['title'] }}</div>
                        @endif
                        <table class="party-lines">
                            @foreach(($party['grid_rows'] ?? []) as $rowGroup)
                                @if(!empty($rowGroup['wide']))
                                    @php $row = $rowGroup['columns'][0] ?? null; @endphp
                                    @if($row)
                                        <tr>
                                            <td colspan="2">
                                                <table class="party-field">
                                                    <tr>
                                                        <td class="party-label">{{ $row['label'] }}:</td>
                                                        <td>{{ $row['value'] }}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    @endif
                                @else
                                    <tr>
                                        @foreach(($rowGroup['columns'] ?? []) as $column)
                                            <td class="party-pair">
                                                <table class="party-field">
                                                    <tr>
                                                        <td class="party-label">{{ $column['label'] }}:</td>
                                                        <td>{{ $column['value'] }}</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        @endforeach
                                        @if(count($rowGroup['columns'] ?? []) === 1)
                                            <td></td>
                                        @endif
                                    </tr>
                                @endif
                            @endforeach
                        </table>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="contract-body">{!! $content_html !!}</div>

        @if($appendixSections->isNotEmpty())
            @foreach($appendixSections as $section)
                <div class="page-break appendix-shell">
                    <div class="appendix-title">Documento anexado ao contrato</div>
                    <div class="appendix-subtitle">{{ $section['original_name'] ?? 'Documento' }}</div>
                    @if(!empty($section['owner_label']))
                        <div class="appendix-owner">{{ $section['owner_label'] }}</div>
                    @endif
                    @foreach(($section['pages'] ?? []) as $page)
                        <div class="appendix-image-wrap">
                            <img src="{{ $page['src'] }}" alt="{{ $section['original_name'] ?? 'Documento anexado' }}" class="appendix-image">
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>

    @if($renderFooterInBody)
        <div class="page-footer {{ $hasCustomFooter ? 'custom-page-footer' : 'default-page-footer' }}">
            @if($hasCustomFooter)
                {!! $rendered_footer_html !!}
            @else
                {{ $settings['footer_text'] ?? 'documento gerado pelo ancora hub' }}
            @endif
        </div>
    @endif
</body>
</html>
