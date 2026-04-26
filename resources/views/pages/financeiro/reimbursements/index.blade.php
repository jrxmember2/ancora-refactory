@extends('layouts.app')

@section('content')
@php($money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.'))

<x-ancora.section-header title="Reembolsos" subtitle="Controle do que o escritorio adiantou, do que o cliente reembolsou e do que ainda permanece pendente.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.reimbursements.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo reembolso</a>
        <x-financeiro.export-actions scope="reimbursements" />
    </div>
</x-ancora.section-header>

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-hand-holding-dollar" title="Sem reembolsos" subtitle="Nenhum reembolso foi cadastrado ate o momento." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                        <th class="px-4 py-3">Codigo</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Processo</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Reembolsado</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $item->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item->code ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->client?->display_name ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->process?->process_number ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $item->type ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $money($item->amount) }}</td>
                            <td class="px-4 py-3">{{ $money($item->reimbursed_amount) }}</td>
                            <td class="px-4 py-3">{{ $reimbursementStatuses[$item->status] ?? $item->status }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('financeiro.reimbursements.edit', $item) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Editar</a>
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
