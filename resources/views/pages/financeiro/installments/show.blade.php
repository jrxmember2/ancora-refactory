@extends('layouts.app')

@section('content')
@php($money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.'))

<x-ancora.section-header :title="$item->code ?: ('Parcelamento #' . $item->id)" subtitle="Detalhamento da parcela, recebivel origem, cobranca gerada e baixas relacionadas.">
    <div class="flex flex-wrap gap-3">
        @if($item->receivable)
            <a href="{{ route('financeiro.receivables.show', $item->receivable) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Abrir recebivel</a>
        @endif
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Valor da parcela" :value="$money($item->amount)" hint="Valor nominal da parcela." icon="fa-solid fa-money-bill" />
    <x-ancora.stat-card label="Numero" :value="$item->installment_number . '/' . $item->installment_total" hint="Sequencia do parcelamento." icon="fa-solid fa-hashtag" />
    <x-ancora.stat-card label="Vencimento" :value="optional($item->due_date)->format('d/m/Y') ?: '-'" hint="Data prevista." icon="fa-solid fa-calendar-days" />
    <x-ancora.stat-card label="Status" :value="$item->status" hint="Situacao da parcela." icon="fa-solid fa-circle-info" />
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 text-sm">
        <div><span class="text-gray-500 dark:text-gray-400">Titulo</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->title ?: '-' }}</div></div>
        <div><span class="text-gray-500 dark:text-gray-400">Contrato</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->contract?->code ?: ($item->contract?->title ?: '-') }}</div></div>
        <div><span class="text-gray-500 dark:text-gray-400">Recebivel origem</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->parentReceivable?->code ?: '-' }}</div></div>
        <div><span class="text-gray-500 dark:text-gray-400">Recebivel gerado</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->receivable?->code ?: '-' }}</div></div>
        <div><span class="text-gray-500 dark:text-gray-400">Cliente</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->receivable?->client?->display_name ?: '-' }}</div></div>
        <div><span class="text-gray-500 dark:text-gray-400">Condominio</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->receivable?->condominium?->name ?: '-' }}</div></div>
    </div>
    @if($item->receivable && $item->receivable->transactions->isNotEmpty())
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Descricao</th>
                        <th class="px-4 py-3">Valor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($item->receivable->transactions as $transaction)
                        <tr>
                            <td class="px-4 py-3">{{ optional($transaction->transaction_date)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $transaction->description ?: '-' }}</td>
                            <td class="px-4 py-3">{{ $money($transaction->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
