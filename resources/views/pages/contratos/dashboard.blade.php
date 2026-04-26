@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Dashboard de Contratos" subtitle="Visão executiva dos contratos, templates, vencimentos e pendências documentais.">
    <div class="flex flex-wrap gap-3">
        <form method="get" class="flex items-center gap-2">
            <select name="year" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                @foreach($years as $optionYear)
                    <option value="{{ $optionYear }}" @selected((int) $year === (int) $optionYear)>{{ $optionYear }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Aplicar</button>
        </form>
        <a href="{{ route('contratos.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
        <a href="{{ route('contratos.create') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Novo contrato</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Total de contratos" :value="$summary['total']" hint="Base geral do módulo." icon="fa-solid fa-file-contract" />
    <x-ancora.stat-card label="Contratos ativos" :value="$summary['ativos']" hint="Status ativo." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Em rascunho" :value="$summary['rascunhos']" hint="Aguardando revisão ou geração final." icon="fa-solid fa-file-pen" />
    <x-ancora.stat-card label="Vencidos" :value="$summary['vencidos']" hint="Prazo encerrado." icon="fa-solid fa-calendar-xmark" />
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Próximos do vencimento" :value="$summary['proximos']" hint="Conforme alerta configurado." icon="fa-solid fa-bell" />
    <x-ancora.stat-card label="Rescindidos" :value="$summary['rescindidos']" hint="Status rescindido." icon="fa-solid fa-ban" />
    <x-ancora.stat-card label="Aguardando assinatura" :value="$summary['assinatura']" hint="Prontos para formalização." icon="fa-solid fa-signature" />
    <x-ancora.stat-card label="Templates cadastrados" :value="$summary['templates']" hint="Modelos ativos do módulo." icon="fa-solid fa-layer-group" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contratos por mês</h3>
        <div class="mt-5"><div id="contractsMonthChart" class="h-[320px]"></div></div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contratos por status</h3>
        <div class="mt-5"><div id="contractsStatusChart" class="h-[320px]"></div></div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contratos por tipo</h3>
        <div class="mt-5 space-y-3">
            @forelse($typeDistribution as $row)
                <div class="rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-medium text-gray-800 dark:text-gray-100">{{ $row['label'] }}</span>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $row['count'] }}</span>
                    </div>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-layer-group" title="Sem contratos por tipo" subtitle="Cadastre contratos para acompanhar a distribuição por documento." />
            @endforelse
        </div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Próximos do vencimento</h3>
        <div class="mt-5"><div id="contractsUpcomingChart" class="h-[320px]"></div></div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    @foreach([
        'upcoming' => ['title' => 'Vencendo nos próximos 30 dias', 'items' => $alerts['upcoming']],
        'without_pdf' => ['title' => 'Contratos sem PDF gerado', 'items' => $alerts['without_pdf']],
        'drafts' => ['title' => 'Contratos em rascunho', 'items' => $alerts['drafts']],
        'without_client' => ['title' => 'Contratos sem cliente vinculado', 'items' => $alerts['without_client']],
        'awaiting_signature' => ['title' => 'Aguardando assinatura', 'items' => $alerts['awaiting_signature']],
    ] as $card)
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $card['title'] }}</h3>
            <div class="mt-4 space-y-3">
                @forelse($card['items'] as $item)
                    <a href="{{ route('contratos.show', $item) }}" class="block rounded-xl border border-gray-200 px-4 py-3 text-sm transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $item->code ?: 'Contrato #' . $item->id }}</div>
                        <div class="mt-1 text-gray-600 dark:text-gray-300">{{ $item->title }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $item->client?->display_name ?: ($item->condominium?->name ?: 'Sem vínculo') }}
                            @if($item->end_date)
                                · {{ optional($item->end_date)->format('d/m/Y') }}
                            @endif
                        </div>
                    </a>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-circle-check" title="Nada por aqui" subtitle="Sem registros para este alerta no momento." />
                @endforelse
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;
    const isDark = document.documentElement.classList.contains('dark');
    const monthLabels = @json($chartData['labels']);
    const monthCounts = @json($chartData['monthCounts']);
    const statusData = @json($statusDistribution);
    const upcomingLabels = @json($chartData['upcomingLabels']);
    const upcomingCounts = @json($chartData['upcomingCounts']);

    const monthEl = document.querySelector('#contractsMonthChart');
    if (monthEl) {
        new ApexCharts(monthEl, {
            chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            series: [{ name: 'Contratos', data: monthCounts }],
            xaxis: { categories: monthLabels },
            colors: ['#941415'],
            dataLabels: { enabled: false },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }

    const statusEl = document.querySelector('#contractsStatusChart');
    if (statusEl && statusData.length) {
        new ApexCharts(statusEl, {
            chart: { type: 'donut', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            labels: statusData.map((row) => row.label),
            series: statusData.map((row) => Number(row.count || 0)),
            colors: ['#941415', '#10b981', '#f59e0b', '#ef4444', '#465fff', '#6b7280', '#0ea5e9', '#8b5cf6'],
            legend: { position: 'bottom' },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }

    const upcomingEl = document.querySelector('#contractsUpcomingChart');
    if (upcomingEl) {
        new ApexCharts(upcomingEl, {
            chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            series: [{ name: 'Vencimentos', data: upcomingCounts }],
            xaxis: { categories: upcomingLabels },
            colors: ['#f59e0b'],
            stroke: { curve: 'smooth', width: 3 },
            dataLabels: { enabled: false },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }
});
</script>
@endpush
