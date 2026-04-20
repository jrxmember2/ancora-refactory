@extends('portal.layouts.app')

@section('content')
<div>
    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#941415]">Cobranças</p>
    <h1 class="mt-2 text-3xl font-semibold text-gray-950">Panorama de cobrança</h1>
    <p class="mt-2 text-sm text-gray-500">Visão executiva das cobranças vinculadas ao seu acesso.</p>
</div>

<section class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-4">
    @foreach([
        ['Total de casos', $summary['total']],
        ['Acordos ativos', $summary['agreements']],
        ['Em negociação', $summary['negotiation']],
        ['Encerrados', $summary['closed']],
    ] as [$label, $value])
        <div class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
            <div class="text-sm text-gray-500">{{ $label }}</div>
            <div class="mt-3 text-3xl font-semibold text-gray-950">{{ $value }}</div>
        </div>
    @endforeach
</section>

<div class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-5 shadow-sm">
    <form method="get" class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar OS, unidade ou devedor..." class="h-12 rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415] md:col-span-2">
        <select name="workflow_stage" class="h-12 rounded-2xl border border-gray-200 px-4 text-sm outline-none focus:border-[#941415]">
            <option value="">Todas as fases</option>
            @foreach($stageLabels as $key => $label)
                <option value="{{ $key }}" @selected(($filters['workflow_stage'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Filtrar</button>
            <a href="{{ route('portal.cobrancas.index') }}" class="rounded-2xl border border-gray-200 px-5 py-3 text-sm font-semibold text-gray-600">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 grid grid-cols-1 gap-4">
    @forelse($items as $item)
        <a href="{{ route('portal.cobrancas.show', $item) }}" class="rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm transition hover:border-[#941415]/40">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-gray-950">{{ $item->os_number }}</div>
                    <div class="mt-2 text-sm text-gray-500">{{ $item->block?->name ? $item->block->name.' · ' : '' }}Unidade {{ $item->unit?->unit_number ?? '-' }}</div>
                    <div class="mt-2 text-sm text-gray-600">Devedor: {{ $item->debtor_name_snapshot }}</div>
                </div>
                <span class="w-fit rounded-full bg-[#f7f2ec] px-3 py-1 text-xs font-semibold text-[#941415]">{{ $stageLabels[$item->workflow_stage] ?? $item->workflow_stage }}</span>
            </div>
        </a>
    @empty
        <div class="rounded-3xl border border-[#eadfd5] bg-white p-8 text-center text-gray-500">Nenhuma cobrança disponível para sua conta.</div>
    @endforelse
</div>

<div class="mt-6">{{ $items->links() }}</div>
@endsection
