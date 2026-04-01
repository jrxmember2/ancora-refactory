@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Propostas" subtitle="Controle comercial com filtros, totais, status, anexos e histórico.">
    <div class="flex gap-3">
        <a href="{{ route('propostas.dashboard') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200"><i class="fa-solid fa-chart-column"></i> Dashboard</a>
        <a href="{{ route('propostas.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white shadow-theme-sm hover:bg-brand-600"><i class="fa-solid fa-plus"></i> Nova proposta</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Total filtrado" :value="'R$ '.number_format($totals['proposal_total'], 2, ',', '.')" hint="Somatório do valor proposto no filtro atual." icon="fa-solid fa-sack-dollar" />
    <x-ancora.stat-card label="Total fechado" :value="'R$ '.number_format($totals['closed_total'], 2, ',', '.')" hint="Somatório das propostas com fechamento informado." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Registros" :value="$proposals->total()" hint="Quantidade de propostas encontradas." icon="fa-solid fa-file-lines" />
    <x-ancora.stat-card label="Página" :value="$proposals->currentPage().' / '.$proposals->lastPage()" hint="Paginação da consulta atual." icon="fa-solid fa-layer-group" />
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por código, cliente, e-mail..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white" />
        <select name="administradora_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Administradora / síndico</option>
            @foreach($filterOptions['administradoras'] as $item)
                <option value="{{ $item->id }}" @selected((int)($filters['administradora_id'] ?? 0) === (int)$item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="service_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Serviço</option>
            @foreach($filterOptions['servicos'] as $item)
                <option value="{{ $item->id }}" @selected((int)($filters['service_id'] ?? 0) === (int)$item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="response_status_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Status</option>
            @foreach($filterOptions['statusRetorno'] as $item)
                <option value="{{ $item->id }}" @selected((int)($filters['response_status_id'] ?? 0) === (int)$item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="send_method_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Forma de envio</option>
            @foreach($filterOptions['formasEnvio'] as $item)
                <option value="{{ $item->id }}" @selected((int)($filters['send_method_id'] ?? 0) === (int)$item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="year" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Ano</option>
            @foreach($filterOptions['years'] as $year)
                <option value="{{ $year }}" @selected((int)($filters['year'] ?? 0) === (int)$year)>{{ $year }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
        <div class="flex gap-3 xl:justify-end">
            <button class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 xl:flex-none"><i class="fa-solid fa-filter"></i> Filtrar</button>
            <a href="{{ route('propostas.index') }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 xl:flex-none">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($proposals->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-file-circle-question" title="Nenhuma proposta encontrada" subtitle="Ajuste os filtros ou cadastre uma nova proposta." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Proposta</th>
                        <th class="px-6 py-4">Cliente</th>
                        <th class="px-6 py-4">Serviço</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Valores</th>
                        <th class="px-6 py-4">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($proposals as $proposal)
                        <tr class="text-sm text-gray-700 dark:text-gray-200">
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $proposal->proposal_code }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($proposal->proposal_date)->format('d/m/Y') }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $proposal->client_name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $proposal->administradora->name ?? '—' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div>{{ $proposal->servico->name ?? '—' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $proposal->formaEnvio->name ?? '—' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium" style="background-color: {{ ($proposal->statusRetorno->color_hex ?? '#999999') }}20; color: {{ $proposal->statusRetorno->color_hex ?? '#999999' }}">{{ $proposal->statusRetorno->name ?? '—' }}</span>
                                @if($proposal->followup_date)
                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Follow-up: {{ optional($proposal->followup_date)->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div>Proposta: <strong>R$ {{ number_format((float)$proposal->proposal_total, 2, ',', '.') }}</strong></div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Fechado: R$ {{ number_format((float)($proposal->closed_total ?? 0), 2, ',', '.') }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('propostas.show', $proposal) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Abrir</a>
                                    <a href="{{ route('propostas.edit', $proposal) }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Editar</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $proposals->links() }}</div>
    @endif
</div>
@endsection
