<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $case->os_number }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm 18mm 18mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.45;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            padding: 20mm 18mm 18mm;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .18);
        }

        .office-header,
        .office-footer {
            color: #64748b;
            font-family: Arial, sans-serif;
            font-size: 8pt;
            text-align: center;
        }

        .office-header {
            border-bottom: 1px solid #d1d5db;
            margin-bottom: 10mm;
            padding-bottom: 4mm;
        }

        .office-footer {
            border-top: 1px solid #d1d5db;
            margin-top: 10mm;
            padding-top: 4mm;
        }

        .term-body {
            white-space: pre-line;
            text-align: justify;
        }

        @media print {
            body {
                background: #fff;
            }

            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <main class="sheet">
        <header class="office-header">
            e-mail: contato@rebecamedina.com.br / telefone: (27) 9.9603-4719
        </header>

        <article class="term-body">{{ $bodyText }}</article>

        <footer class="office-footer">
            Termo gerado pela OS {{ $case->os_number }} · {{ $templateType === 'judicial' ? 'unidade ajuizada' : 'unidade não ajuizada' }}
        </footer>
    </main>
</body>
</html>
