@extends('layouts.app')

@section('content')
@php
    $summary = $data['summary'];
    $charts = $data['charts'];
    $alerts = $data['alerts'];
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

<x-ancora.section-header title="Financeiro 360" subtitle="Painel executivo do escritorio com caixa, faturamento, inadimplencia, despesas e performance financeira.">
    <div class="flex flex-wrap gap-3">
        <form method="get" class="flex items-center gap-2">
            <select name="year" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                @foreach($years as $optionYear)
                    <option value="{{ $optionYear }}" @selected((int) $year === (int) $optionYear)>{{ $optionYear }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Aplicar</button>
        </form>
        <a href="{{ route('financeiro.receivables.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Receber</a>
        <a href="{{ route('financeiro.payables.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Pagar</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Receita do mes" :value="$money($summary['receita_mes'])" hint="Competencia corrente." icon="fa-solid fa-sack-dollar" />
    <x-ancora.stat-card label="Receita recebida" :value="$money($summary['receita_recebida'])" hint="Entradas liquidadas." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Receita pendente" :value="$money($summary['receita_pendente'])" hint="Titulos em aberto." icon="fa-solid fa-hourglass-half" />
    <x-ancora.stat-card label="Receita vencida" :value="$money($summary['receita_vencida'])" hint="Saldo atrasado." icon="fa-solid fa-triangle-exclamation" />
    <x-ancora.stat-card label="Despesas do mes" :value="$money($summary['despesas_mes'])" hint="Compromissos do periodo." icon="fa-solid fa-file-invoice-dollar" />
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Saldo liquido" :value="$money($summary['saldo_liquido'])" hint="Entradas menos saidas." icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Caixa atual" :value="$money($summary['caixa_atual'])" hint="Somatorio das contas ativas." icon="fa-solid fa-wallet" />
    <x-ancora.stat-card label="Ticket medio" :value="$money($summary['ticket_medio'])" hint="Media de recebimentos do mes." icon="fa-solid fa-chart-line" />
    <x-ancora.stat-card label="Receita recorrente" :value="$money($summary['receita_recorrente'])" hint="Base recorrente contratual." icon="fa-solid fa-repeat" />
    <x-ancora.stat-card label="Receita extraordinaria" :value="$money($summary['receita_extraordinaria'])" hint="Receitas fora do padrao recorrente." icon="fa-solid fa-bolt" />
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Contratos faturando" :value="$summary['contratos_faturando']" hint="Com geracao financeira ativa." icon="fa-solid fa-file-signature" />
    <x-ancora.stat-card label="Contas vencidas" :value="$summary['contas_vencidas']" hint="Receber e pagar em atraso." icon="fa-solid fa-calendar-xmark" />
    <x-ancora.stat-card label="Contas a vencer" :value="$summary['contas_a_vencer']" hint="Janela dos proximos dias." icon="fa-solid fa-bell" />
    <x-ancora.stat-card label="Inadimplencia" :value="$money($summary['inadimplencia'])" hint="Saldo total em atraso." icon="fa-solid fa-ban" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Receita mensal</h3>
        <div class="mt-5"><div id="financialRevenueChart" class="h-[320px]"></div></div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Entradas x Saidas</h3>
        <div class="mt-5"><div id="financialFlowChart" class="h-[320px]"></div></div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Receitas por cliente</h3>
        <div class="mt-5"><div id="financialClientChart" class="h-[320px]"></div></div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Receitas por condominio</h3>
        <div class="mt-5"><div id="financialCondominiumChart" class="h-[320px]"></div></div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Categorias financeiras</h3>
        <div class="mt-5"><div id="financialCategoryChart" class="h-[320px]"></div></div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Despesas por centro de custo</h3>
        <div class="mt-5"><div id="financialCostCenterChart" class="h-[320px]"></div></div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contas vencidas</h3>
        <div class="mt-4 space-y-3">
            @forelse($alerts['contas_vencidas'] as $item)
                <a href="{{ route('financeiro.receivables.show', $item) }}" class="block rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $item->code ?: 'Recebivel #' . $item->id }}</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-300">{{ $item->title }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($item->due_date)->format('d/m/Y') }} · {{ $money((float) $item->final_amount - (float) $item->received_amount) }}</div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-circle-check" title="Sem contas vencidas" subtitle="Nenhum titulo atrasado foi encontrado." />
            @endforelse
        </div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Caixa negativo</h3>
        <div class="mt-4 space-y-3">
            @forelse($alerts['caixa_negativo'] as $item)
                <div class="rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</div>
                    <div class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $money($item['balance']) }} de saldo atual</div>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-wallet" title="Sem contas negativas" subtitle="Todas as contas ativas estao com saldo saudavel." />
            @endforelse
        </div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contratos sem cobranca</h3>
        <div class="mt-4 space-y-3">
            @forelse($alerts['contratos_sem_cobranca'] as $item)
                <a href="{{ route('contratos.show', $item) }}" class="block rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $item->code ?: 'Contrato #' . $item->id }}</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-300">{{ $item->title }}</div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-file-circle-check" title="Sem pendencias" subtitle="Todos os contratos faturaveis ja possuem cobranca associada." />
            @endforelse
        </div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Custas sem reembolso</h3>
        <div class="mt-4 space-y-3">
            @forelse($alerts['custas_sem_reembolso'] as $item)
                <a href="{{ route('financeiro.process-costs.edit', $item) }}" class="block rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $item->code ?: 'Custa #' . $item->id }}</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-300">{{ $item->cost_type ?: 'Custa processual' }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $money($item->amount) }}</div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-scale-balanced" title="Sem custas pendentes" subtitle="Nao ha custas sem reembolso pendente no momento." />
            @endforelse
        </div>
    </div>
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Saldo por banco e conta</h3>
    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach($data['accounts'] as $account)
            <div class="rounded-xl border border-gray-200 px-4 py-4 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $account['name'] }}</div>
                <div class="mt-2 text-xl font-semibold {{ $account['balance'] < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-gray-900 dark:text-white' }}">{{ $money($account['balance']) }}</div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;

    const isDark = document.documentElement.classList.contains('dark');
    const labels = @json($charts['months']);
    const receita = @json($charts['receita_mensal']);
    const despesa = @json($charts['despesa_mensal']);
    const saldo = @json($charts['saldo_mensal']);
    const clientes = @json($charts['clientes']);
    const condominios = @json($charts['condominios']);
    const categorias = @json($charts['categorias']);
    const centros = @json($charts['centros']);

    const barOptions = {
        chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
        dataLabels: { enabled: false },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
        theme: { mode: isDark ? 'dark' : 'light' },
    };

    const lineOptions = {
        chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        theme: { mode: isDark ? 'dark' : 'light' },
    };

    const revenueEl = document.querySelector('#financialRevenueChart');
    if (revenueEl) {
        new ApexCharts(revenueEl, {
            ...barOptions,
            series: [{ name: 'Receita', data: receita }],
            xaxis: { categories: labels },
            colors: ['#16a34a'],
        }).render();
    }

    const flowEl = document.querySelector('#financialFlowChart');
    if (flowEl) {
        new ApexCharts(flowEl, {
            ...lineOptions,
            series: [
                { name: 'Entradas', data: receita },
                { name: 'Saidas', data: despesa },
                { name: 'Saldo', data: saldo },
            ],
            xaxis: { categories: labels },
            colors: ['#16a34a', '#dc2626', '#465fff'],
        }).render();
    }

    const clientEl = document.querySelector('#financialClientChart');
    if (clientEl && clientes.length) {
        new ApexCharts(clientEl, {
            ...barOptions,
            series: [{ name: 'Receita', data: clientes.map((item) => Number(item.amount || 0)) }],
            xaxis: { categories: clientes.map((item) => item.label) },
            colors: ['#0ea5e9'],
        }).render();
    }

    const condominiumEl = document.querySelector('#financialCondominiumChart');
    if (condominiumEl && condominios.length) {
        new ApexCharts(condominiumEl, {
            ...barOptions,
            series: [{ name: 'Receita', data: condominios.map((item) => Number(item.amount || 0)) }],
            xaxis: { categories: condominios.map((item) => item.label) },
            colors: ['#8b5cf6'],
        }).render();
    }

    const categoryEl = document.querySelector('#financialCategoryChart');
    if (categoryEl && categorias.length) {
        new ApexCharts(categoryEl, {
            chart: { type: 'donut', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            labels: categorias.map((item) => item.label),
            series: categorias.map((item) => Number(item.amount || 0)),
            colors: ['#16a34a', '#0ea5e9', '#f59e0b', '#8b5cf6', '#dc2626', '#6366f1', '#ec4899', '#475569'],
            legend: { position: 'bottom' },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }

    const costCenterEl = document.querySelector('#financialCostCenterChart');
    if (costCenterEl && centros.length) {
        new ApexCharts(costCenterEl, {
            ...barOptions,
            series: [{ name: 'Despesas', data: centros.map((item) => Number(item.amount || 0)) }],
            xaxis: { categories: centros.map((item) => item.label) },
            colors: ['#f97316'],
        }).render();
    }
});
</script>
@endpush
