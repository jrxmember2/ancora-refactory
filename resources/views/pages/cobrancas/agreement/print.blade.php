@include('pages.cobrancas.agreement.document', [
    'case' => $case,
    'title' => $title,
    'bodyText' => $bodyText,
    'templateType' => $templateType,
    'payload' => $payload ?? [],
    'autoPrint' => true,
    'pdfMode' => false,
])
