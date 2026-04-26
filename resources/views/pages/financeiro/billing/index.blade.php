@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

<x-ancora.section-header title="Faturamento" subtitle="Gere cobrancas financeiras a partir de contratos recorrentes, evitando duplicidade e mantendo a previsao do escritorio.">
    <x-financeiro.export-actions scope="billing" />
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <div class="flex gap-3 xl:col-span-2">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Atualizar periodo</button>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-file-circle-check" title="Sem contratos faturaveis" subtitle="Nao ha contratos aptos a gerar cobranca no periodo informado." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                        <th class="px-4 py-3">Contrato</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Condominio</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Recorrencia</th>
                        <th class="px-4 py-3">Geradas no periodo</th>
                        <th class="px-4 py-3 text-right">Acao</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $row)
                        @php($contract = $row['contract'])
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $contract->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $contract->code ?: '#' . $contract->id }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $contract->title }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $contract->client?->display_name ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $contract->condominium?->name ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $money($contract->monthly_value ?: $contract->contract_value ?: $contract->total_value) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $recurrences[$contract->recurrence ?? 'mensal'] ?? ($contract->recurrence ?: 'Mensal') }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row['generated'] }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="post" action="{{ route('financeiro.billing.generate-contract', $contract) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
                                    <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">
                                    <button class="rounded-xl bg-brand-500 px-4 py-2 text-xs font-medium text-white">Gerar cobrancas</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
