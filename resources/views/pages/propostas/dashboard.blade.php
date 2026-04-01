@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Dashboard de Propostas" subtitle="Painel comercial priorizado na reescrita big bang.">
    <form method="get" class="flex gap-3">
        <select name="year" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            @foreach(($summary['years'] ?: [now()->year]) as $year)
                <option value="{{ $year }}" @selected((int) $summary['year'] === (int) $year)>{{ $year }}</option>
            @endforeach
        </select>
        <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">Aplicar</button>
    </form>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Total proposto" :value="'R$ '.number_format($summary['totals']['proposal_total'], 2, ',', '.')" hint="Ano selecionado" icon="fa-solid fa-sack-dollar" />
    <x-ancora.stat-card label="Total fechado" :value="'R$ '.number_format($summary['totals']['closed_total'], 2, ',', '.')" hint="Aprovadas / fechadas" icon="fa-solid fa-handshake" />
    <x-ancora.stat-card label="Total declinado" :value="'R$ '.number_format($summary['totals']['declined_total'], 2, ',', '.')" hint="Perdas registradas" icon="fa-solid fa-ban" />
    <x-ancora.stat-card label="Alertas" :value="count($summary['alerts'])" hint="Follow-ups vencidos ou de hoje" icon="fa-solid fa-bell" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.25fr,0.75fr]">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Propostas por mês</h3>
        <div id="proposalChart" class="mt-6 h-[320px]"></div>
    </div>
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Top serviços</h3>
            <div class="mt-4 space-y-4">
                @forelse($summary['services'] as $service)
                    <div>
                        <div class="flex items-center justify-between text-sm"><span class="text-gray-700 dark:text-gray-200">{{ $service->name }}</span><strong class="text-gray-900 dark:text-white">{{ $service->total }}</strong></div>
                        <div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full bg-brand-500" style="width: {{ min(100, $summary['services'][0]->total > 0 ? ($service->total / $summary['services'][0]->total * 100) : 0) }}%"></div></div>
                    </div>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-list-check" title="Sem dados" subtitle="Ainda não há serviços contabilizados neste ano." />
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Alertas de follow-up</h3>
            <div class="mt-4 space-y-4">
                @forelse($summary['alerts'] as $alert)
                    <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 dark:border-warning-900/40 dark:bg-warning-950/20">
                        <div class="text-sm font-semibold text-warning-800 dark:text-warning-300">{{ $alert->proposal_code }} · {{ $alert->client_name }}</div>
                        <div class="mt-1 text-xs text-warning-700 dark:text-warning-400">{{ \Carbon\Carbon::parse($alert->followup_date)->format('d/m/Y') }} · {{ $alert->status_name }}</div>
                    </div>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-bell-slash" title="Sem alertas" subtitle="Nenhum follow-up crítico para o período." />
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;
    const options = {
        chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
        series: [{ name: 'Propostas', data: @json($summary['monthly_values']) }],
        xaxis: { categories: @json($summary['monthly_labels']) },
        colors: ['#465fff'],
        grid: { borderColor: '#e4e7ec' },
        theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
        plotOptions: { bar: { borderRadius: 8, columnWidth: '45%' } },
        dataLabels: { enabled: false },
        stroke: { show: false }
    };
    new ApexCharts(document.querySelector('#proposalChart'), options).render();
});
</script>
@endpush
