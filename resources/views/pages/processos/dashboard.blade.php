@extends('layouts.app')

@php
    $softButtonClass = 'rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $date = fn ($value) => $value ? $value->format('d/m/Y') : 'Sem data';
@endphp

@section('content')
<x-ancora.section-header title="Dashboard de Processos" subtitle="Panorama completo dos processos cadastrados, valores, andamentos e sincronizacao DataJud.">
    <div class="flex flex-wrap gap-3">
        <form method="get" class="flex items-center gap-2">
            <select name="year" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                @foreach(($years->isNotEmpty() ? $years : collect([now()->year])) as $optionYear)
                    <option value="{{ $optionYear }}" @selected((int) $year === (int) $optionYear)>{{ $optionYear }}</option>
                @endforeach
            </select>
            <button class="{{ $buttonClass }}">Aplicar</button>
        </form>
        <a href="{{ route('processos.index') }}" class="{{ $softButtonClass }}">Lista de processos</a>
        <a href="{{ route('processos.create') }}" class="{{ $softButtonClass }}">Novo processo</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Processos cadastrados" :value="$summary['total']" hint="Total acessivel ao usuario." icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Ativos" :value="$summary['active']" hint="Sem data de encerramento." icon="fa-solid fa-folder-open" />
    <x-ancora.stat-card label="Encerrados" :value="$summary['closed']" hint="Com encerramento informado." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Novos no ano" :value="$summary['year_total']" :hint="'Ano selecionado: '.$year" icon="fa-solid fa-calendar-plus" />
    <x-ancora.stat-card label="Processos no mes" :value="$summary['month_total']" :hint="'Competencia: '.$summary['month_label']" icon="fa-solid fa-calendar-days" />
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Particulares" :value="$summary['private']" hint="Respeitando visibilidade restrita." icon="fa-solid fa-lock" />
    <x-ancora.stat-card label="DataJud configurado" :value="$summary['datajud_ready']" hint="Processo e tribunal preenchidos." icon="fa-solid fa-cloud-arrow-down" />
    <x-ancora.stat-card label="DataJud sincronizado" :value="$summary['datajud_synced']" hint="Com pelo menos uma busca executada." icon="fa-solid fa-rotate" />
    <x-ancora.stat-card label="Movimentos no ano" :value="$summary['movements_year']" :hint="$summary['datajud_movements_year'].' DataJud / '.$summary['manual_movements_year'].' manuais'" icon="fa-solid fa-timeline" />
    <x-ancora.stat-card label="Movimentos no mes" :value="$summary['movements_month']" :hint="'Competencia: '.$summary['month_label']" icon="fa-solid fa-bell" />
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Valor da causa" :value="$money($summary['claim_amount'])" hint="Soma dos processos visiveis." icon="fa-solid fa-file-invoice-dollar" />
    <x-ancora.stat-card label="Provisionado" :value="$money($summary['provisioned_amount'])" hint="Risco financeiro estimado." icon="fa-solid fa-chart-pie" />
    <x-ancora.stat-card label="Pago em juizo" :value="$money($summary['court_paid_amount'])" hint="Depositos / pagamentos judiciais." icon="fa-solid fa-building-columns" />
    <x-ancora.stat-card label="Custos" :value="$money($summary['process_cost_amount'])" hint="Custos do processo." icon="fa-solid fa-receipt" />
    <x-ancora.stat-card label="Sentencas" :value="$money($summary['sentence_amount'])" hint="Valores de sentenca cadastrados." icon="fa-solid fa-gavel" />
</div>

<div class="mt-6 grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Evolucao operacional</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Novos processos e movimentacoes no ano selecionado.</p>
            </div>
            <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-200">{{ $year }}</span>
        </div>
        <div class="mt-6 min-w-0 overflow-hidden">
            <div id="processEvolutionChart" class="h-[320px] min-w-0 max-w-full"></div>
        </div>
    </div>

    <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Processos por status</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Distribuicao do acervo atual por situacao cadastrada.</p>
        </div>
        @if(count($statusDistribution))
            <div class="mt-6 min-w-0 overflow-hidden">
                <div id="processStatusChart" class="h-[320px] min-w-0 max-w-full"></div>
            </div>
        @else
            <div class="mt-6">
                <x-ancora.empty-state icon="fa-solid fa-chart-pie" title="Sem dados de status" subtitle="Cadastre processos para visualizar esta distribuicao." />
            </div>
        @endif
    </div>
</div>

<div class="mt-6 grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-2">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Panorama financeiro</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Comparativo dos valores cadastrados nos processos visiveis.</p>
        </div>
        <div class="mt-6 min-w-0 overflow-hidden">
            <div id="processFinancialChart" class="h-[280px] min-w-0 max-w-full"></div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tipos e natureza</h3>
        <div class="mt-5 space-y-5">
            <div>
                <div class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Tipo de processo</div>
                <div class="space-y-3">
                    @forelse($typeDistribution as $row)
                        <div>
                            <div class="flex justify-between gap-3 text-sm">
                                <span class="font-medium text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $row['count'] }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ $summary['total'] > 0 ? min(100, round(($row['count'] / $summary['total']) * 100)) : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sem tipos cadastrados.</p>
                    @endforelse
                </div>
            </div>
            <div>
                <div class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Natureza</div>
                <div class="space-y-3">
                    @forelse(array_slice($natureDistribution, 0, 5) as $row)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 px-3 py-2 dark:border-gray-800">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $row['label'] }}</span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sem natureza cadastrada.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Processos recentes</h3>
            <a href="{{ route('processos.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Ver todos</a>
        </div>
        <div class="mt-4 space-y-3">
            @forelse($latestCases as $item)
                <a href="{{ route('processos.show', $item) }}" class="block rounded-2xl border border-gray-200 p-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->process_number ?: 'Processo #' . $item->id }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->client_name_snapshot ?: 'Cliente nao informado' }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->processTypeOption?->name ?: 'Tipo nao informado' }} &middot; {{ $item->phases_count }} fase(s)</div>
                        </div>
                        @php($statusColor = $item->statusOption?->color_hex ?: '#6B7280')
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold text-white" style="background-color: {{ $statusColor }}">{{ $item->statusOption?->name ?: 'Sem status' }}</span>
                    </div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-folder-open" title="Sem processos" subtitle="Cadastre o primeiro processo para iniciar o controle." />
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ultimas movimentacoes</h3>
        <div class="mt-4 space-y-3">
            @forelse($latestPhases as $phase)
                @if($phase->processCase)
                    <a href="{{ route('processos.show', ['processo' => $phase->processCase, 'tab' => 'fases']) }}" class="block rounded-2xl border border-gray-200 p-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full {{ $phase->source === 'datajud' ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }} px-2.5 py-1 text-xs font-semibold">{{ $phase->source === 'datajud' ? 'DataJud' : 'Manual' }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $date($phase->phase_date) }}</span>
                        </div>
                        <div class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $phase->description }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $phase->processCase->process_number ?: 'Processo #' . $phase->processCase->id }}</div>
                    </a>
                @endif
            @empty
                <x-ancora.empty-state icon="fa-solid fa-timeline" title="Sem movimentacoes" subtitle="As fases manuais ou importadas pelo DataJud aparecerao aqui." />
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Acompanhar de perto</h3>
            <span class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">{{ $summary['stale_90'] }} sem 90d+</span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Processos ativos ha mais tempo sem fase registrada.</p>
        <div class="mt-4 space-y-3">
            @forelse($attentionCases as $row)
                @php($item = $row['case'])
                <a href="{{ route('processos.show', $item) }}" class="block rounded-2xl border border-gray-200 p-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->process_number ?: 'Processo #' . $item->id }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->client_name_snapshot ?: 'Cliente nao informado' }}</div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Ultima fase: {{ $row['last_movement_date'] ? $row['last_movement_date']->format('d/m/Y') : 'sem fase' }}
                        @if($row['days_without_movement'] !== null)
                            &middot; {{ (int) $row['days_without_movement'] }} dia(s)
                        @endif
                    </div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-circle-check" title="Tudo em dia" subtitle="Nao ha processos ativos pendentes de acompanhamento." />
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;

    const labels = @json($chartData['labels']);
    const caseCounts = @json($chartData['caseCounts']);
    const movementCounts = @json($chartData['movementCounts']);
    const manualMovementCounts = @json($chartData['manualMovementCounts']);
    const datajudMovementCounts = @json($chartData['datajudMovementCounts']);
    const statusData = @json($statusDistribution);
    const financialLabels = @json($chartData['financialLabels']);
    const financialTotals = @json($chartData['financialTotals']);
    const isDark = document.documentElement.classList.contains('dark');
    const moneyFormatter = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    const evolutionEl = document.querySelector('#processEvolutionChart');
    if (evolutionEl) {
        new ApexCharts(evolutionEl, {
            chart: { type: 'line', height: 320, width: '100%', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            series: [
                { name: 'Novos processos', type: 'column', data: caseCounts },
                { name: 'Movimentacoes', type: 'line', data: movementCounts },
                { name: 'DataJud', type: 'area', data: datajudMovementCounts },
                { name: 'Manuais', type: 'area', data: manualMovementCounts },
            ],
            xaxis: { categories: labels },
            yaxis: { labels: { formatter: (value) => Number(value || 0).toLocaleString('pt-BR') } },
            colors: ['#941415', '#465fff', '#10b981', '#f59e0b'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: [0, 3, 2, 2] },
            fill: { type: ['solid', 'solid', 'gradient', 'gradient'], gradient: { opacityFrom: 0.24, opacityTo: 0.04 } },
            grid: { borderColor: isDark ? '#1f2937' : '#e5e7eb' },
            legend: { position: 'top', horizontalAlign: 'left' },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            tooltip: { y: { formatter: (value) => Number(value || 0).toLocaleString('pt-BR') } },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }

    const statusEl = document.querySelector('#processStatusChart');
    if (statusEl && statusData.length > 0) {
        new ApexCharts(statusEl, {
            chart: { type: 'donut', height: 320, width: '100%', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            labels: statusData.map((row) => row.label),
            series: statusData.map((row) => Number(row.count || 0)),
            colors: statusData.map((row) => row.color || '#6B7280'),
            legend: { position: 'bottom' },
            dataLabels: { enabled: true },
            stroke: { width: 2 },
            tooltip: { y: { formatter: (value) => `${Number(value || 0)} processo(s)` } },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }

    const financialEl = document.querySelector('#processFinancialChart');
    if (financialEl) {
        new ApexCharts(financialEl, {
            chart: { type: 'bar', height: 280, width: '100%', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            series: [{ name: 'Valor', data: financialTotals }],
            xaxis: { categories: financialLabels, labels: { formatter: (value) => moneyFormatter(value) } },
            yaxis: { labels: { maxWidth: 160 } },
            colors: ['#941415'],
            dataLabels: { enabled: false },
            grid: { borderColor: isDark ? '#1f2937' : '#e5e7eb' },
            plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '48%' } },
            tooltip: { y: { formatter: (value) => moneyFormatter(value) } },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }
});
</script>
@endpush
