<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->title }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @php
        $margins = array_merge(['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3], $contract->template?->margins_json ?? []);
        $appendixSections = collect($appendixSections ?? [])->filter(fn ($item) => !empty($item['pages']))->values();
        $displayTitle = trim((string) ($rendered_title ?? '')) !== '';
        $contractingParty = $parties['contracting'] ?? [];
        $contractedParty = $parties['contracted'] ?? [];
    @endphp
    <style>
        @page {
            size: A4 {{ ($contract->template?->page_orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait' }};
            margin: {{ (float) ($margins['top'] ?? 3) }}cm {{ (float) ($margins['right'] ?? 2) }}cm {{ (float) ($margins['bottom'] ?? 2) }}cm {{ (float) ($margins['left'] ?? 3) }}cm;
        }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 13px; line-height: 1.65; margin: 0; }
        .wrapper { width: 100%; }
        .default-header { border-bottom: 3px solid #941415; padding-bottom: 16px; margin-bottom: 24px; }
        .default-header-table { width: 100%; border-collapse: collapse; }
        .default-header-title { text-align: right; }
        .default-title { font-size: 24px; font-weight: 700; color: #941415; text-transform: uppercase; line-height: 1.2; }
        .default-code { font-size: 12px; color: #6b7280; margin-top: 6px; }
        .custom-fragment { margin-bottom: 24px; }
        .custom-fragment table { width: 100%; border-collapse: collapse; }
        .custom-fragment td, .custom-fragment th { border: 1px solid #d1d5db; padding: 8px; }
        .party-shell { margin-bottom: 24px; }
        .party-grid { width: 100%; border-collapse: separate; border-spacing: 12px 0; }
        .party-card-shell { width: 50%; vertical-align: top; }
        .party-card { border: 1px solid #d8a3a4; border-radius: 18px; background: #fff7f7; padding: 16px 18px; }
        .party-title { margin-bottom: 12px; font-size: 11px; font-weight: 700; color: #a73738; text-transform: uppercase; letter-spacing: 0.14em; }
        .party-lines { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
        .party-lines td { border: none; padding: 0; vertical-align: top; }
        .party-pair { width: 50%; padding-right: 10px; }
        .party-field { width: 100%; border-collapse: collapse; }
        .party-field td { border: none; padding: 0; vertical-align: top; }
        .party-label { width: 88px; font-weight: 700; color: #6b7280; white-space: nowrap; padding-right: 6px; }
        .contract-body { min-height: 380px; }
        .contract-body table { width: 100%; border-collapse: collapse; }
        .contract-body table td, .contract-body table th { border: 1px solid #d1d5db; padding: 8px; }
        .contract-body img, .custom-fragment img { max-width: 100%; height: auto; }
        .page-break { break-before: page; page-break-before: always; }
        .appendix-shell { margin-top: 0; }
        .appendix-title { font-size: 15px; font-weight: 700; color: #941415; text-transform: uppercase; letter-spacing: 0.08em; }
        .appendix-subtitle { margin-top: 6px; font-size: 12px; color: #6b7280; }
        .appendix-owner { margin-top: 2px; font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em; }
        .appendix-image-wrap { margin-top: 18px; text-align: center; }
        .appendix-image { max-width: 100%; max-height: 25cm; object-fit: contain; }
        .footer { margin-top: 28px; border-top: 2px solid #941415; padding-top: 10px; font-size: 11px; color: #6b7280; text-align: center; text-transform: lowercase; }
        .ancora-page-number::before { content: counter(page); }
        .ancora-page-total::before { content: counter(pages); }
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
        @elseif(!empty($contractingParty['rows']) || !empty($contractedParty['rows']))
            <div class="party-shell">
                <table class="party-grid">
                    <tr>
                        @foreach([$contractingParty, $contractedParty] as $party)
                            <td class="party-card-shell">
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
                            </td>
                        @endforeach
                    </tr>
                </table>
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
                </div>
                @foreach(($section['pages'] ?? []) as $page)
                    <div class="{{ $loop->first ? '' : 'page-break' }}">
                        <div class="appendix-image-wrap">
                            <img src="{{ $page['src'] }}" alt="{{ $section['original_name'] ?? 'Documento anexado' }}" class="appendix-image">
                        </div>
                    </div>
                @endforeach
            @endforeach
        @endif

        @if(!empty($rendered_footer_html))
            <div class="custom-fragment" style="margin-top: 20px;">{!! $rendered_footer_html !!}</div>
        @endif

        <div class="footer">{{ $settings['footer_text'] ?? 'documento gerado pelo âncora hub' }}</div>
    </div>
</body>
</html>
