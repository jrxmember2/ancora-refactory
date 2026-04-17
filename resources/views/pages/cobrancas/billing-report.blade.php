@extends('layouts.app')

@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $activeFilters = array_filter($filters ?? [], fn ($value) => $value !== '' && $value !== null);
@endphp

@section('content')
<x-ancora.section-header title="Faturamento de cobrança" subtitle="Fechamento financeiro por condomínio, bloco e unidade, com valores recebidos, projetados e honorários.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista de OS</a>
        <a href="{{ route('cobrancas.billing.report.pdf', $activeFilters) }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Exportar PDF</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-6">
        <select name="billing_status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Todos os status</option>
            @foreach($filterOptions['billingStatuses'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['billing_status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="condominium_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Todos os condomínios</option>
            @foreach($filterOptions['condominiums'] as $item)
                <option value="{{ $item->id }}" @selected((int) ($filters['condominium_id'] ?? 0) === (int) $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="charge_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Todos os tipos</option>
            @foreach($filterOptions['chargeTypes'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['charge_type'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="billing_date_from" value="{{ $filters['billing_date_from'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white" title="Faturado de">
        <input type="date" name="billing_date_to" value="{{ $filters['billing_date_to'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white" title="Faturado até">
        <div class="flex flex-wrap gap-3">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('cobrancas.billing.report') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="OS no relatório" :value="$totals['cases_count']" hint="Registros encontrados pelos filtros." icon="fa-solid fa-file-invoice-dollar" />
    <x-ancora.stat-card label="Total dos acordos" :value="$money($totals['agreement_total'])" hint="Soma do valor total negociado." icon="fa-solid fa-scale-balanced" />
    <x-ancora.stat-card label="Valor recebido" :value="$money($totals['paid_amount'])" hint="Entrada, parcela única ou primeira parcela paga." icon="fa-solid fa-circle-dollar-to-slot" />
    <x-ancora.stat-card label="Valor projetado" :value="$money($totals['projected_amount'])" hint="Saldo futuro ainda projetado." icon="fa-solid fa-chart-line" />
    <x-ancora.stat-card label="Honorários" :value="$money($totals['fees_amount'])" hint="Honorários vinculados aos acordos." icon="fa-solid fa-briefcase" />
</div>

<div class="mt-6 space-y-5">
    @forelse($groups as $group)
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-6 py-4 dark:border-gray-800 dark:bg-gray-900/40 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $group['condominium'] }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $group['totals']['cases_count'] }} OS · recebido {{ $money($group['totals']['paid_amount']) }} · honorários {{ $money($group['totals']['fees_amount']) }}</p>
                </div>
                <div class="text-sm font-semibold text-brand-700 dark:text-brand-200">Acordos: {{ $money($group['totals']['agreement_total']) }}</div>
            </div>

            @foreach($group['blocks'] as $block)
                <div class="px-6 py-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">{{ $block['block'] }}</h4>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $block['totals']['cases_count'] }} unidade(s)</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-100 text-xs uppercase tracking-[0.14em] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <th class="px-3 py-3">Unidade</th>
                                    <th class="px-3 py-3">OS / devedor</th>
                                    <th class="px-3 py-3">Tipo</th>
                                    <th class="px-3 py-3 text-right">Acordo</th>
                                    <th class="px-3 py-3 text-right">Recebido</th>
                                    <th class="px-3 py-3 text-right">Projetado</th>
                                    <th class="px-3 py-3 text-right">Honorários</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($block['rows'] as $row)
                                    <tr>
                                        <td class="px-3 py-3 text-sm font-medium text-gray-900 dark:text-white">{{ $row['unit'] }}</td>
                                        <td class="px-3 py-3">
                                            <a href="{{ route('cobrancas.show', $row['id']) }}" class="text-sm font-semibold text-brand-600 dark:text-brand-300">{{ $row['os_number'] }}</a>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['debtor'] }}</div>
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-200">{{ $row['charge_type_label'] }}</td>
                                        <td class="px-3 py-3 text-right text-sm text-gray-700 dark:text-gray-200">{{ $money($row['agreement_total']) }}</td>
                                        <td class="px-3 py-3 text-right text-sm text-gray-700 dark:text-gray-200">
                                            {{ $money($row['paid_amount']) }}
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['paid_label'] }}</div>
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm text-gray-700 dark:text-gray-200">{{ $money($row['projected_amount']) }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ $money($row['fees_amount']) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 text-sm font-semibold text-gray-900 dark:bg-gray-900/40 dark:text-white">
                                    <td class="px-3 py-3" colspan="3">Subtotal do bloco</td>
                                    <td class="px-3 py-3 text-right">{{ $money($block['totals']['agreement_total']) }}</td>
                                    <td class="px-3 py-3 text-right">{{ $money($block['totals']['paid_amount']) }}</td>
                                    <td class="px-3 py-3 text-right">{{ $money($block['totals']['projected_amount']) }}</td>
                                    <td class="px-3 py-3 text-right">{{ $money($block['totals']['fees_amount']) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <x-ancora.empty-state icon="fa-solid fa-file-invoice-dollar" title="Nenhum faturamento encontrado" subtitle="Ajuste os filtros para visualizar OS a faturar, faturadas ou por condomínio." />
        </div>
    @endforelse
</div>

@if($totals['cases_count'] > 0)
    <div class="mt-6 rounded-2xl border border-brand-200 bg-brand-50 p-6 text-brand-900 dark:border-brand-900/50 dark:bg-brand-500/10 dark:text-brand-100">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div><span class="block text-xs uppercase tracking-[0.16em] opacity-70">Total de acordos</span><strong class="mt-1 block text-lg">{{ $money($totals['agreement_total']) }}</strong></div>
            <div><span class="block text-xs uppercase tracking-[0.16em] opacity-70">Total recebido</span><strong class="mt-1 block text-lg">{{ $money($totals['paid_amount']) }}</strong></div>
            <div><span class="block text-xs uppercase tracking-[0.16em] opacity-70">Total projetado</span><strong class="mt-1 block text-lg">{{ $money($totals['projected_amount']) }}</strong></div>
            <div><span class="block text-xs uppercase tracking-[0.16em] opacity-70">Total honorários</span><strong class="mt-1 block text-lg">{{ $money($totals['fees_amount']) }}</strong></div>
        </div>
    </div>
@endif
@endsection
