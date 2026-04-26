@extends('layouts.app')

@section('content')
@php($money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.'))

<x-ancora.section-header title="Inadimplencia" subtitle="Titulos vencidos, saldo em atraso e filtro de recebiveis pendentes com foco em cobranca e retomada de caixa.">
    <x-financeiro.export-actions scope="delinquency" />
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-2">
    <x-ancora.stat-card label="Titulos vencidos" :value="$summary['quantidade']" hint="Quantidade total filtrada." icon="fa-solid fa-calendar-xmark" />
    <x-ancora.stat-card label="Saldo em atraso" :value="$money($summary['valor'])" hint="Somatorio de saldo aberto vencido." icon="fa-solid fa-triangle-exclamation" />
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-circle-check" title="Sem inadimplencia" subtitle="Nao ha titulos vencidos em aberto com os filtros atuais." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                        <th class="px-4 py-3">Codigo</th>
                        <th class="px-4 py-3">Titulo</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Condominio</th>
                        <th class="px-4 py-3">Unidade</th>
                        <th class="px-4 py-3">Vencimento</th>
                        <th class="px-4 py-3">Saldo</th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $item->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item->code ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->title }}</td>
                            <td class="px-4 py-3">{{ $item->client?->display_name ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->condominium?->name ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->unit?->unit_number ?: '-' }}</td>
                            <td class="px-4 py-3">{{ optional($item->due_date)->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-4 py-3 text-rose-600 dark:text-rose-300">{{ $money((float) $item->final_amount - (float) $item->received_amount) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('financeiro.receivables.show', $item) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Abrir</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    @endif
</div>
@endsection
