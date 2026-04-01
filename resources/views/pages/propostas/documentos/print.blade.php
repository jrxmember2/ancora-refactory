@php($renderData = $render)
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $renderData['document']['document_title'] ?? 'Proposta Premium' }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/proposal-template-aquarela.css') }}">
</head>
<body class="proposal-print" onload="window.print()">
    @include('pages.propostas.documentos.templates.aquarela_master', ['renderData' => $renderData])
</body>
</html>
