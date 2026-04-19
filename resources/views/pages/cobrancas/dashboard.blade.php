@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Dashboard de cobrança" subtitle="Visão operacional das OS de cobrança, negociações e judicialização.">
    <div class="flex flex-wrap gap-3">
        <form method="get" class="flex items-center gap-2">
            <select name="year" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                @foreach(($years->isNotEmpty() ? $years : collect([now()->year])) as $optionYear)
                    <option value="{{ $optionYear }}" @selected((int) $year === (int) $optionYear)>{{ $optionYear }}</option>
                @endforeach
            </select>
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Aplicar</button>
        </form>
        <a href="{{ route('cobrancas.billing.report') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.03]">Faturamento</a>
        <a href="{{ route('cobrancas.import.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.03]">Importar inadimplência</a>
        <a href="{{ route('cobrancas.create') }}" class="rounded-xl border border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">Nova OS</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="OS no ano" :value="$summary['total']" :hint="'Ano selecionado: '.$year" icon="fa-solid fa-folder-open" />
    <x-ancora.stat-card label="OS no mês" :value="$summary['month_total']" :hint="'Mês de referência: '.$summary['month_label']" icon="fa-solid fa-calendar-days" />
    <x-ancora.stat-card label="Acordos no ano" :value="'R$ '.number_format((float) $summary['agreement_total'], 2, ',', '.')" :hint="'Entradas somadas: R$ '.number_format((float) $summary['entry_total'], 2, ',', '.')" icon="fa-solid fa-money-bill-wave" />
    <x-ancora.stat-card label="Acordos no mês" :value="'R$ '.number_format((float) $summary['agreement_month_total'], 2, ',', '.')" :hint="'Competência: '.$summary['month_label']" icon="fa-solid fa-chart-simple" />
    <x-ancora.stat-card label="Honorários do mês" :value="'R$ '.number_format((float) $summary['fees_month_total'], 2, ',', '.')" :hint="'Competência: '.$summary['month_label']" icon="fa-solid fa-briefcase" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Honorários anual" :value="'R$ '.number_format((float) $summary['fees_total'], 2, ',', '.')" :hint="'Ano selecionado: '.$year" icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Aptas para notificar" :value="$summary['notificar']" hint="Prontas para acionar WhatsApp/e-mail." icon="fa-solid fa-paper-plane" />
    <x-ancora.stat-card label="Em negociação" :value="$summary['negociacao']" hint="OS com tratativa ativa." icon="fa-solid fa-comments-dollar" />
    <x-ancora.stat-card label="Aguardando assinatura" :value="$summary['aguardando_assinatura']" hint="Termos aguardando aceite." icon="fa-solid fa-file-signature" />
    <x-ancora.stat-card label="Acordos ativos" :value="$summary['acordo_ativo']" hint="Parcelamentos em execução." icon="fa-solid fa-receipt" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
    <x-ancora.stat-card label="Aptas para judicializar" :value="$summary['judicializar']" hint="Casos prontos para virar ação." icon="fa-solid fa-gavel" />
    <x-ancora.stat-card label="Ajuizados" :value="$summary['ajuizado']" hint="OS já levadas ao judicial." icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Encerrados" :value="$summary['encerrado']" hint="Pagos / finalizados." icon="fa-solid fa-circle-check" />
</div>

<div class="mt-6 grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Evolução dos acordos</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Acompanhamento mensal dos acordos e honorários no ano selecionado.</p>
            </div>
            <a href="{{ route('cobrancas.billing.report') }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Abrir faturamento</a>
        </div>
        <div class="mt-6 min-w-0 overflow-hidden">
            <div id="cobrancaAgreementChart" class="h-[320px] min-w-0 max-w-full"></div>
        </div>
        <div class="mt-6 min-w-0 overflow-hidden rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">OS criadas por mês</h4>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Volume mensal de novas OS para comparar operação e conversão em acordos.</p>
                </div>
                <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-200">{{ $year }}</span>
            </div>
            <div class="mt-4 min-w-0 overflow-hidden">
                <div id="cobrancaOsChart" class="h-[180px] min-w-0 max-w-full"></div>
            </div>
        </div>
    </div>

    <div class="min-w-0 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">OS recentes</h3>
            <a href="{{ route('cobrancas.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Ver todas</a>
        </div>
        <div class="mt-4 space-y-3">
            @forelse($latestCases as $item)
                <a href="{{ route('cobrancas.show', $item) }}" class="block rounded-2xl border border-gray-200 p-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->os_number }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->condominium?->name ?? 'Condomínio não vinculado' }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->block?->name ? $item->block->name.' · ' : '' }}Unidade {{ $item->unit?->unit_number ?? '—' }}</div>
                        </div>
                        <span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $stageLabels[$item->workflow_stage] ?? $item->workflow_stage }}</span>
                    </div>
                    <div class="mt-3 text-sm text-gray-700 dark:text-gray-200">{{ $item->debtor_name_snapshot }}</div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-folder-open" title="Sem OS cadastradas" subtitle="Crie a primeira OS de cobrança para começar o fluxo." />
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
    const agreementTotals = @json($chartData['agreementTotals']);
    const feesTotals = @json($chartData['feesTotals']);
    const caseCounts = @json($chartData['caseCounts']);
    const isDark = document.documentElement.classList.contains('dark');
    const moneyFormatter = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    const agreementChartEl = document.querySelector('#cobrancaAgreementChart');
    if (agreementChartEl) {
        new ApexCharts(agreementChartEl, {
            chart: { type: 'area', height: 320, width: '100%', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            series: [
                { name: 'Acordos', data: agreementTotals },
                { name: 'Honorários', data: feesTotals },
            ],
            xaxis: { categories: labels },
            yaxis: { labels: { formatter: (value) => moneyFormatter(value) } },
            colors: ['#941415', '#465fff'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.34, opacityTo: 0.04 } },
            grid: { borderColor: isDark ? '#1f2937' : '#e5e7eb' },
            legend: { position: 'top', horizontalAlign: 'left' },
            tooltip: { y: { formatter: (value) => moneyFormatter(value) } },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }

    const osChartEl = document.querySelector('#cobrancaOsChart');
    if (osChartEl) {
        new ApexCharts(osChartEl, {
            chart: { type: 'bar', height: 180, width: '100%', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
            series: [{ name: 'OS criadas', data: caseCounts }],
            xaxis: { categories: labels },
            colors: ['#f59e0b'],
            dataLabels: { enabled: false },
            grid: { borderColor: isDark ? '#1f2937' : '#e5e7eb' },
            plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
            tooltip: { y: { formatter: (value) => `${Number(value || 0)} OS` } },
            theme: { mode: isDark ? 'dark' : 'light' },
        }).render();
    }
});
</script>
@endpush
