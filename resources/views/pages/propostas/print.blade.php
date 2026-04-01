<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Impressão {{ $proposal->proposal_code }}</title>
    <style>
        body{font-family:Arial,sans-serif;padding:24px;color:#111}h1{margin:0 0 12px}table{width:100%;border-collapse:collapse;margin-top:16px}td,th{padding:8px;border:1px solid #ddd;text-align:left}.muted{color:#666;font-size:12px}@media print{body{padding:0}}
    </style>
</head>
<body onload="window.print()">
    <h1>Proposta {{ $proposal->proposal_code }}</h1>
    <div class="muted">{{ optional($proposal->proposal_date)->format('d/m/Y') }}</div>
    <table>
        <tr><th>Cliente</th><td>{{ $proposal->client_name }}</td></tr>
        <tr><th>Solicitante</th><td>{{ $proposal->requester_name }}</td></tr>
        <tr><th>Administradora</th><td>{{ $proposal->administradora->name ?? '—' }}</td></tr>
        <tr><th>Serviço</th><td>{{ $proposal->servico->name ?? '—' }}</td></tr>
        <tr><th>Forma de envio</th><td>{{ $proposal->formaEnvio->name ?? '—' }}</td></tr>
        <tr><th>Status</th><td>{{ $proposal->statusRetorno->name ?? '—' }}</td></tr>
        <tr><th>Valor da proposta</th><td>R$ {{ number_format((float) $proposal->proposal_total, 2, ',', '.') }}</td></tr>
        <tr><th>Valor fechado</th><td>R$ {{ number_format((float) ($proposal->closed_total ?? 0), 2, ',', '.') }}</td></tr>
        <tr><th>Telefone</th><td>{{ $proposal->requester_phone ?: '—' }}</td></tr>
        <tr><th>Email</th><td>{{ $proposal->contact_email ?: '—' }}</td></tr>
        <tr><th>Follow-up</th><td>{{ optional($proposal->followup_date)->format('d/m/Y') }}</td></tr>
        <tr><th>Validade</th><td>{{ $proposal->validity_days }} dias</td></tr>
        <tr><th>Observações</th><td>{{ $proposal->notes ?: '—' }}</td></tr>
    </table>
</body>
</html>
