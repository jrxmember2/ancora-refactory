@extends('layouts.app')

@section('content')
@php($money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.'))

<x-ancora.section-header title="Cobrancas Financeiras" subtitle="Fila de titulos configurados para cobranca automatica, com etapa atual e saldo aberto por recebivel.">
    <x-financeiro.export-actions scope="collection" />
</x-ancora.section-header>

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-bell" title="Sem cobrancas na fila" subtitle="Nenhum recebivel com cobranca automatica ativa foi encontrado." /></div>
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
                        <th class="px-4 py-3">Vencimento</th>
                        <th class="px-4 py-3">Etapa</th>
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
                            <td class="px-4 py-3">{{ optional($item->due_date)->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $stageLabels[$item->collection_stage] ?? ($item->collection_stage ?: 'Nao iniciado') }}</td>
                            <td class="px-4 py-3">{{ $money((float) $item->final_amount - (float) $item->received_amount) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('financeiro.receivables.show', $item) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Abrir recebivel</a>
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
