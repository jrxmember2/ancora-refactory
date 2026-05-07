@php($renderData = $render)
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $renderData['document']['document_title'] ?? 'Proposta Premium' }}</title>
    @if(!empty($inlineCss))
        <style>{!! $inlineCss !!}</style>
    @endif
</head>
<body class="proposal-print">
    @include('pages.propostas.documentos.templates.aquarela_master', ['renderData' => $renderData])
</body>
</html>
