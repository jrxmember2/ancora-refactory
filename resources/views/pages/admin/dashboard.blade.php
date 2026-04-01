@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Dashboard Executivo" subtitle="Área reservada para o dashboard unificado dos módulos do Âncora." />
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-ancora.stat-card label="Status" value="Shell pronto" hint="Rota preservada para o dashboard consolidado futuro." icon="fa-solid fa-chart-line" />
    <x-ancora.stat-card label="Arquitetura" value="Laravel + TailAdmin" hint="Base pronta para widgets, KPIs e indicadores multi-módulo." icon="fa-solid fa-layer-group" />
    <x-ancora.stat-card label="Deploy" value="EasyPanel" hint="Estrutura preparada para web, worker e scheduler." icon="fa-solid fa-server" />
</div>
<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <p class="text-sm leading-7 text-gray-600 dark:text-gray-300">Neste corte da reescrita, o dashboard executivo fica como placeholder estratégico. O dashboard funcional que já foi portado é o de <strong>Propostas</strong>, onde o mecanismo comercial foi priorizado.</p>
</div>
@endsection
