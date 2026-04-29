<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->title }}</title>
    @php
        $margins = array_merge(['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3], $contract->template?->margins_json ?? []);
        $metaBlocks = collect($meta_blocks ?? [])->filter(fn ($item) => trim((string) ($item['value'] ?? '')) !== '')->values();
    @endphp
    <style>
        @page {
            size: A4 {{ ($contract->template?->page_orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait' }};
            margin: {{ (float) ($margins['top'] ?? 3) }}cm {{ (float) ($margins['right'] ?? 2) }}cm {{ (float) ($margins['bottom'] ?? 2) }}cm {{ (float) ($margins['left'] ?? 3) }}cm;
        }
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 13px; line-height: 1.65; margin: 0; }
        .wrapper { width: 100%; }
        .header { border-bottom: 3px solid #941415; padding-bottom: 16px; margin-bottom: 24px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-title { text-align: right; }
        .title { font-size: 24px; font-weight: 700; color: #941415; text-transform: uppercase; line-height: 1.2; }
        .code { font-size: 12px; color: #6b7280; margin-top: 6px; }
        .meta-shell { margin-bottom: 24px; padding: 18px; border: 1px solid #d8a3a4; border-radius: 18px; background: #fff8f8; }
        .meta-grid { width: 100%; border-collapse: separate; border-spacing: 0; }
        .meta-card { border: 1px solid #ebc5c6; border-radius: 14px; padding: 12px 14px; background: #ffffff; vertical-align: top; }
        .meta-label { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700; color: #a73738; text-transform: uppercase; letter-spacing: 0.08em; }
        .meta-value { font-size: 13px; color: #1f2937; line-height: 1.55; }
        .body { min-height: 380px; }
        .body table { width: 100%; border-collapse: collapse; }
        .body table td, .body table th { border: 1px solid #d1d5db; padding: 8px; }
        .closing { margin-top: 28px; }
        .footer { margin-top: 28px; border-top: 2px solid #941415; padding-top: 10px; font-size: 11px; color: #6b7280; text-align: center; text-transform: lowercase; }
    </style>
</head>
<body @if(!empty($autoPrint)) onload="window.print()" @endif>
    <div class="wrapper">
        <div class="header">
            <table class="header-table">
                <tr>
                    <td>
                        @if(($settings['show_logo'] ?? false) && !empty($brand['logo_light']))
                            <img src="{{ asset(ltrim($brand['logo_light'], '/')) }}" alt="Logo" style="max-height: 70px;">
                        @endif
                    </td>
                    <td class="header-title">
                        <div class="title">{{ $contract->title }}</div>
                        <div class="code">{{ $contract->code ?: 'Sem codigo' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        @if(!empty($contract->template?->header_html))
            <div>{!! $contract->template->header_html !!}</div>
        @endif

        @if($metaBlocks->isNotEmpty())
            <div class="meta-shell">
                <table class="meta-grid">
                    @foreach($metaBlocks->chunk(2) as $chunk)
                        <tr>
                            @foreach($chunk as $block)
                                <td style="width: 50%; padding: 8px;">
                                    <div class="meta-card">
                                        <span class="meta-label">{{ $block['label'] }}</span>
                                        <div class="meta-value">{{ $block['value'] }}</div>
                                    </div>
                                </td>
                            @endforeach
                            @if($chunk->count() === 1)
                                <td style="width: 50%; padding: 8px;"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div class="body">{!! $content_html !!}</div>

        <div class="closing">
            <p>{{ ucfirst($location_label ?: 'Vitoria/ES') }}, {{ $date_long }}.</p>
        </div>

        @if(!empty($contract->template?->footer_html))
            <div style="margin-top: 20px;">{!! $contract->template->footer_html !!}</div>
        @endif

        <div class="footer">{{ $settings['footer_text'] ?? 'documento gerado pelo âncora hub' }}</div>
    </div>
</body>
</html>
