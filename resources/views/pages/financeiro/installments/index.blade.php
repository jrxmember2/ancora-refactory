@extends('layouts.app')

@section('content')
@php($money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.'))

<x-ancora.section-header title="Parcelamentos" subtitle="Controle das parcelas geradas em negociacoes e faturamentos derivados de recebiveis do Financeiro 360.">
    <x-financeiro.export-actions scope="installments" />
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="flex flex-wrap gap-3">
        <select name="status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os status</option>
            <option value="aberto" @selected(($filters['status'] ?? '') === 'aberto')>Aberto</option>
            <option value="parcial" @selected(($filters['status'] ?? '') === 'parcial')>Parcial</option>
            <option value="recebido" @selected(($filters['status'] ?? '') === 'recebido')>Recebido</option>
        </select>
        <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-layer-group" title="Sem parcelamentos" subtitle="Nenhum parcelamento foi encontrado com os filtros informados." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                        <th class="px-4 py-3">Codigo</th>
                        <th class="px-4 py-3">Titulo</th>
                        <th class="px-4 py-3">Contrato</th>
                        <th class="px-4 py-3">Origem</th>
                        <th class="px-4 py-3">Parcela</th>
                        <th class="px-4 py-3">Vencimento</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $item->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item->code ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->title ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->contract?->code ?: ($item->contract?->title ?: '-') }}</td>
                            <td class="px-4 py-3">{{ $item->parentReceivable?->code ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->installment_number }}/{{ $item->installment_total }}</td>
                            <td class="px-4 py-3">{{ optional($item->due_date)->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $money($item->amount) }}</td>
                            <td class="px-4 py-3">{{ $item->status }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('financeiro.installments.show', $item) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Visualizar</a>
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
