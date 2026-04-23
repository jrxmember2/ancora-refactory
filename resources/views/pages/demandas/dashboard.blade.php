@extends('layouts.app')

@php
    $cardClass = 'rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
@endphp

@section('content')
<x-ancora.section-header title="Dashboard de Demandas" subtitle="Panorama operacional das demandas, tags e SLAs.">
    <a href="{{ route('demandas.kanban') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Abrir kanban</a>
    <a href="{{ route('demandas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
</x-ancora.section-header>

<section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-6">
    @foreach([
        ['Total', $summary['total'], 'fa-solid fa-inbox', 'text-brand-600'],
        ['Abertas', $summary['open'], 'fa-solid fa-folder-open', 'text-blue-600'],
        ['SLA vencido', $summary['overdue'], 'fa-solid fa-triangle-exclamation', 'text-error-600'],
        ['SLA a vencer', $summary['at_risk'], 'fa-solid fa-hourglass-half', 'text-warning-600'],
        ['Aguardando cliente', $summary['waiting_client'], 'fa-solid fa-user-clock', 'text-sky-600'],
        ['Concluidas no mes', $summary['closed_month'], 'fa-solid fa-circle-check', 'text-success-600'],
    ] as [$label, $value, $icon, $color])
        <div class="{{ $cardClass }}">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <i class="{{ $icon }} {{ $color }}"></i>
            </div>
            <div class="mt-3 text-3xl font-semibold text-gray-900 dark:text-white">{{ $value }}</div>
        </div>
    @endforeach
</section>

<section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1fr,420px]">
    <div class="{{ $cardClass }}">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Demandas por tag</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Distribuicao atual do fluxo operacional.</p>
            </div>
            <a href="{{ route('config.index') }}#demand-catalog-section" class="text-sm font-medium text-brand-600 dark:text-brand-300">Configurar tags</a>
        </div>
        <div class="mt-5 space-y-4">
            @forelse($tagDistribution as $row)
                @php($tag = $row['tag'])
                @php($percent = $summary['total'] > 0 ? min(100, round(($row['total'] / $summary['total']) * 100)) : 0)
                <div>
                    <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                        <div class="flex items-center gap-2 font-medium text-gray-800 dark:text-gray-100">
                            <span class="h-3 w-3 rounded-full" style="background-color: {{ $tag->color_hex }}"></span>
                            {{ $tag->name }}
                        </div>
                        <div class="text-gray-500 dark:text-gray-400">{{ $row['open'] }} aberta(s) · {{ $row['total'] }} total</div>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full" style="width: {{ $percent }}%; background-color: {{ $tag->color_hex }}"></div>
                    </div>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-tags" title="Sem tags" subtitle="Cadastre tags em Configuracoes para habilitar o kanban." />
            @endforelse
        </div>
    </div>

    <div class="{{ $cardClass }}">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Alertas de SLA</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Vencidas e a vencer com menos de 10% do prazo restante.</p>
        <div class="mt-5 space-y-3">
            @forelse($slaAttention as $item)
                <a href="{{ route('demandas.show', $item) }}" class="block rounded-2xl border border-gray-100 p-4 transition hover:border-brand-300 dark:border-gray-800">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $item->protocol }}</div>
                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $item->subject }}</div>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $item->slaStatus() === 'overdue' ? 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-200' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-200' }}">
                            {{ $item->slaStatusLabel() }}
                        </span>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">Prazo: {{ $item->sla_due_at?->format('d/m/Y H:i') ?: '-' }}</div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nenhum SLA critico agora.</div>
            @endforelse
        </div>
    </div>
</section>

<section class="mt-6 {{ $cardClass }}">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Ultimas demandas</h2>
        <a href="{{ route('demandas.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Ver lista</a>
    </div>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
        @forelse($latestDemands as $item)
            <a href="{{ route('demandas.show', $item) }}" class="rounded-2xl border border-gray-100 p-4 transition hover:border-brand-300 dark:border-gray-800">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-sm font-semibold text-brand-600 dark:text-brand-300">{{ $item->protocol }}</div>
                    @if($item->tag)
                        <span class="h-3 w-3 rounded-full" style="background-color: {{ $item->tag->color_hex }}"></span>
                    @endif
                </div>
                <div class="mt-2 line-clamp-2 text-sm font-medium text-gray-900 dark:text-white">{{ $item->subject }}</div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item->clientName() }}</div>
            </a>
        @empty
            <div class="md:col-span-2 xl:col-span-4">
                <x-ancora.empty-state icon="fa-solid fa-inbox" title="Sem demandas" subtitle="As solicitacoes abertas pelo portal aparecerao aqui." />
            </div>
        @endforelse
    </div>
</section>
@endsection
