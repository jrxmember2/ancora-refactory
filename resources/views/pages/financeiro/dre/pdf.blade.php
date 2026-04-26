@php
    $brandName = $brand['app_name'] ?? 'Ancora';
    $companyName = $brand['company_name'] ?? ($brand['app_company'] ?? 'Escritorio');
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>DRE</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; padding: 24px; font-size: 12px; }
        h1 { color: #941415; margin: 0; font-size: 22px; }
        .meta { color: #6b7280; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; text-transform: uppercase; font-size: 10px; }
        .summary { margin-top: 18px; padding: 14px; background: #f9fafb; border: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>DRE</h1>
    <div class="meta">
        Periodo: {{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }}<br>
        Emitido em {{ now()->format('d/m/Y H:i') }} · {{ $brandName }} · {{ $companyName }}
    </div>

    <div class="summary">
        <strong>Resumo</strong><br>
        Receita bruta: {{ $money($data['summary']['receita_bruta']) }}<br>
        Receita liquida: {{ $money($data['summary']['receita_liquida']) }}<br>
        Custos: {{ $money($data['summary']['custos']) }}<br>
        Despesas: {{ $money($data['summary']['despesas']) }}<br>
        Resultado: {{ $money($data['summary']['resultado']) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Grupo</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['groups'] as $group)
                <tr>
                    <td>{{ $group['label'] }}</td>
                    <td>{{ $money($group['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
