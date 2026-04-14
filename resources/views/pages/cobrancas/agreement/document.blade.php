@php
    $branding = $ancoraBrand ?? [];
    $payload = $payload ?? [];
    $autoPrint = $autoPrint ?? true;
    $pdfMode = $pdfMode ?? false;

    $logoPath = $branding['logo_light'] ?? '/imgs/logomarca.svg';
    if ($pdfMode && !preg_match('#^https?://#i', (string) $logoPath)) {
        $logoSrc = 'file:///' . ltrim(str_replace('\\', '/', public_path(ltrim((string) $logoPath, '/'))), '/');
    } else {
        $logoSrc = $logoPath;
    }

    $footerContacts = collect([
        $branding['company_email'] ?? null,
        $branding['company_phone'] ?? null,
    ])->filter(fn ($item) => trim((string) $item) !== '')->implode(' · ');
    if ($footerContacts === '') {
        $office = $payload['law_office'] ?? [];
        $footerContacts = collect([
            $office['email'] ?? null,
            $office['phone'] ?? null,
        ])->filter(fn ($item) => trim((string) $item) !== '')->implode(' · ');
    }

    $paragraphs = preg_split('/\R{2,}/u', trim((string) $bodyText)) ?: [];
    $paragraphs = collect($paragraphs)
        ->map(fn ($item) => trim((string) $item))
        ->filter()
        ->values()
        ->all();

    if (($paragraphs[0] ?? '') === 'TERMO DE CONFISSÃO DE DÍVIDA E ACORDO EXTRAJUDICIAL') {
        array_shift($paragraphs);
    }

    $dateIndex = null;
    foreach ($paragraphs as $index => $paragraph) {
        if (preg_match('/,\s*\d{1,2}\s+de\s+.+\s+de\s+\d{4}\.?$/iu', $paragraph)) {
            $dateIndex = $index;
            break;
        }
    }
    $bodyParagraphs = $dateIndex === null ? $paragraphs : array_slice($paragraphs, 0, $dateIndex);

    $formatParagraph = function (string $paragraph) {
        $escaped = e($paragraph);
        $escaped = preg_replace('/\b(TERMO DE CONFISSÃO DE DÍVIDAS)\b/iu', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/\b(credor|devedora|devedor)\b/iu', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/^(CLÁUSULA\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ]+:)/u', '<strong>$1</strong>', $escaped);
        return nl2br($escaped, false);
    };

    $creditorSignature = $payload['creditor_signature'] ?? 'CONDOMÍNIO CREDOR';
    $debtorSignature = $payload['debtor_signature'] ?? 'DEVEDOR(A)';
    $debtorLabel = $payload['debtor_label'] ?? 'DEVEDOR(A)';
    $witnesses = $payload['witnesses'] ?? [];
    $signatureCity = $payload['signature_city'] ?? 'Vitória/ES';
    $signatureDate = $payload['signature_date'] ?? now()->translatedFormat('j \d\e F \d\e Y');
@endphp

<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Termo de acordo</title>
    <style>
        @page {
            size: A4;
            margin: 30mm 20mm 20mm 30mm;
        }

        :root {
            --page-width: 210mm;
            --page-height: 297mm;
            --page-margin-top: 30mm;
            --page-margin-right: 20mm;
            --page-margin-bottom: 20mm;
            --page-margin-left: 30mm;
            --printable-width: 160mm;
            --printable-height: 247mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            background: #eef0f3;
            color: #111827;
            font-family: "Times New Roman", Times, serif;
            font-size: 11.4pt;
            line-height: 1.38;
        }

        .sheet {
            width: var(--page-width);
            min-height: var(--page-height);
            margin: 0 auto;
            background: #fff;
            padding: var(--page-margin-top) var(--page-margin-right) var(--page-margin-bottom) var(--page-margin-left);
            box-shadow: 0 20px 70px rgba(15, 23, 42, .18);
        }

        .sheet-table {
            display: table;
            width: 100%;
            height: var(--printable-height);
            min-height: var(--printable-height);
            table-layout: fixed;
        }

        .term-header-row,
        .term-content-row,
        .term-footer-row {
            display: table-row;
        }

        .term-header,
        .term-content,
        .term-footer {
            display: table-cell;
        }

        .term-content-row {
            height: 100%;
        }

        .term-header {
            border-bottom: 2.5px solid #941415;
            padding-bottom: 6mm;
            text-align: center;
        }

        .brand-logo {
            display: inline-block;
            max-height: 24mm;
            max-width: 62mm;
            object-fit: contain;
        }

        .term-content {
            padding: 7mm 0 8mm;
            text-align: justify;
            vertical-align: top;
        }

        .term-title {
            margin: 0 0 7mm;
            text-align: center;
            font-size: 13pt;
            font-weight: 700;
            letter-spacing: .01em;
            text-transform: uppercase;
        }

        .term-paragraph {
            margin: 0 0 3.4mm;
        }

        .term-paragraph--qualification {
            font-weight: 700;
        }

        .signature-date {
            margin: 8mm 0 11mm;
            text-align: center;
        }

        .signature-grid {
            font-size: 0;
            margin-top: 4mm;
        }

        .signature-block {
            display: inline-block;
            width: 45%;
            min-height: 22mm;
            margin: 0 2.5% 13mm;
            text-align: center;
            vertical-align: top;
            font-size: 11.4pt;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .signature-line {
            border-top: 1.4px solid #111827;
            margin-bottom: 2.5mm;
            padding-top: 2mm;
        }

        .signature-name {
            font-weight: 700;
            text-transform: uppercase;
        }

        .signature-role {
            margin-top: 1mm;
            font-size: 10.5pt;
        }

        .term-footer {
            border-top: 2.5px solid #941415;
            padding-top: 4mm;
            color: #374151;
            font-family: Arial, sans-serif;
            font-size: 8.4pt;
            text-align: center;
            vertical-align: bottom;
        }

        @media print {
            body {
                background: #fff;
            }

            .sheet {
                width: auto;
                min-height: var(--printable-height);
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .sheet-table {
                height: var(--printable-height);
                min-height: var(--printable-height);
            }
        }
    </style>
</head>
<body @if($autoPrint) onload="window.print()" @endif>
    <main class="sheet">
        <div class="sheet-table">
            <div class="term-header-row">
                <header class="term-header">
                    <img src="{{ $logoSrc }}" alt="Logo" class="brand-logo">
                </header>
            </div>

            <div class="term-content-row">
                <section class="term-content">
                    <h1 class="term-title">TERMO DE CONFISSÃO DE DÍVIDA E ACORDO EXTRAJUDICIAL</h1>

                    @foreach($bodyParagraphs as $index => $paragraph)
                        <p class="term-paragraph {{ $index <= 1 ? 'term-paragraph--qualification' : '' }}">{!! $formatParagraph($paragraph) !!}</p>
                    @endforeach

                    <div class="signature-date">{{ $signatureCity }}, {{ $signatureDate }}.</div>

                    <div class="signature-grid">
                        <div class="signature-block">
                            <div class="signature-line">
                                <div class="signature-name">{{ $creditorSignature }}</div>
                                <div class="signature-role">p.p/ Rebeca de Paula - OAB/ES 25.057</div>
                                <div class="signature-role">CREDOR</div>
                            </div>
                        </div>

                        <div class="signature-block">
                            <div class="signature-line">
                                <div class="signature-name">{{ $debtorSignature }}</div>
                                <div class="signature-role">{{ $debtorLabel }}</div>
                            </div>
                        </div>

                        @foreach($witnesses as $witness)
                            <div class="signature-block">
                                <div class="signature-line">
                                    <div class="signature-name">{{ $witness['name'] ?? 'TESTEMUNHA' }}</div>
                                    <div class="signature-role">CPF: {{ $witness['document'] ?? '' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="term-footer-row">
                <footer class="term-footer">
                    {{ $footerContacts }}
                </footer>
            </div>
        </div>
    </main>
</body>
</html>
