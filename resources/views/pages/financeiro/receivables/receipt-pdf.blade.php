@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $brandName = $brand['app_name'] ?? 'Ancora';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $item->code }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; padding: 24px; font-size: 12px; }
        h1 { color: #941415; margin: 0; font-size: 22px; }
        .box { margin-top: 18px; padding: 16px; border: 1px solid #d1d5db; }
        .sign { margin-top: 60px; text-align: center; }
    </style>
</head>
<body>
    <h1>Recibo de recebimento</h1>
    <div class="box">
        Recebemos de <strong>{{ $item->client?->display_name ?: ($item->condominium?->name ?: 'Cliente nao informado') }}</strong> o valor de
        <strong>{{ $money($item->received_amount ?: $item->final_amount) }}</strong>, referente ao titulo <strong>{{ $item->title }}</strong>.
        <br><br>
        Documento: {{ $item->code ?: '-' }}<br>
        Vencimento: {{ optional($item->due_date)->format('d/m/Y') ?: '-' }}<br>
        Data de emissao: {{ now()->format('d/m/Y H:i') }}<br>
        Sistema: {{ $brandName }}
    </div>
    <div class="sign">
        ___________________________________________<br>
        Assinatura
    </div>
</body>
</html>
