@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $balance = (float) $item->amount - (float) $item->paid_amount;
@endphp

<x-ancora.section-header :title="$item->code ?: $item->title" subtitle="Detalhamento da conta a pagar, liquidacoes, anexos e rastreabilidade da despesa.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.payables.edit', $item) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Editar</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr,1fr]">
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
            <x-ancora.stat-card label="Valor" :value="$money($item->amount)" hint="Valor original da despesa." icon="fa-solid fa-file-invoice-dollar" />
            <x-ancora.stat-card label="Pago" :value="$money($item->paid_amount)" hint="Total quitado." icon="fa-solid fa-circle-check" />
            <x-ancora.stat-card label="Saldo" :value="$money($balance)" hint="Saldo em aberto." icon="fa-solid fa-hourglass-half" />
            <x-ancora.stat-card label="Status" :value="$payableStatuses[$item->status] ?? $item->status" hint="Situacao atual." icon="fa-solid fa-circle-info" />
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 text-sm">
                <div><span class="text-gray-500 dark:text-gray-400">Titulo</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->title }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Fornecedor</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->supplier?->display_name ?: ($item->supplier_name_snapshot ?: '-') }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Categoria</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->category?->name ?: '-' }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Centro de custo</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->costCenter?->name ?: '-' }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Conta</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->account?->name ?: '-' }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Vencimento</span><div class="font-medium text-gray-900 dark:text-white">{{ optional($item->due_date)->format('d/m/Y') ?: '-' }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Competencia</span><div class="font-medium text-gray-900 dark:text-white">{{ optional($item->competence_date)->format('d/m/Y') ?: '-' }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Forma</span><div class="font-medium text-gray-900 dark:text-white">{{ $paymentMethods[$item->payment_method] ?? ($item->payment_method ?: '-') }}</div></div>
                <div><span class="text-gray-500 dark:text-gray-400">Responsavel</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->responsible?->name ?: '-' }}</div></div>
            </div>
            @if($item->notes)
                <div class="mt-5 rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">{{ $item->notes }}</div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Pagamentos realizados</h3>
            @if($item->transactions->isEmpty())
                <div class="mt-4"><x-ancora.empty-state icon="fa-solid fa-receipt" title="Sem pagamentos" subtitle="Nenhuma baixa foi registrada para esta conta." /></div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3">Conta</th>
                                <th class="px-4 py-3">Descricao</th>
                                <th class="px-4 py-3">Forma</th>
                                <th class="px-4 py-3">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($item->transactions as $transaction)
                                <tr>
                                    <td class="px-4 py-3">{{ optional($transaction->transaction_date)->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3">{{ $transaction->account?->name ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $transaction->description ?: '-' }}</td>
                                    <td class="px-4 py-3">{{ $paymentMethods[$transaction->payment_method] ?? ($transaction->payment_method ?: '-') }}</td>
                                    <td class="px-4 py-3 text-rose-600 dark:text-rose-300">{{ $money($transaction->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Registrar pagamento</h3>
            <form method="post" action="{{ route('financeiro.payables.settle', $item) }}" class="mt-4 space-y-4">
                @csrf
                <input type="text" name="settlement_amount" value="{{ number_format(max($balance, 0), 2, ',', '.') }}" placeholder="Valor do pagamento" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <input type="date" name="settlement_date" value="{{ now()->format('Y-m-d') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <select name="account_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Conta</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
                <select name="payment_method" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Forma</option>
                    @foreach($paymentMethods as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <textarea name="description" rows="4" placeholder="Observacao do pagamento" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700"></textarea>
                <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Registrar pagamento</button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos</h3>
            <form method="post" action="{{ route('financeiro.payables.attachments.upload', $item) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <input type="file" name="files[]" multiple class="block text-sm text-gray-600 dark:text-gray-300">
                <input type="text" name="file_type" placeholder="Tipo do arquivo" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <input type="text" name="description" placeholder="Descricao" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <button class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Enviar anexos</button>
            </form>
            <div class="mt-4 space-y-3">
                @forelse($item->attachments as $attachment)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $attachment->original_name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $attachment->description ?: ($attachment->file_type ?: 'Arquivo') }} · {{ optional($attachment->created_at)->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('financeiro.payables.attachments.download', [$item, $attachment]) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Baixar</a>
                            <form method="post" action="{{ route('financeiro.payables.attachments.delete', [$item, $attachment]) }}">@csrf<button class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-medium text-rose-600 dark:border-rose-900 dark:text-rose-300">Excluir</button></form>
                        </div>
                    </div>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-paperclip" title="Sem anexos" subtitle="Nenhum documento foi vinculado a esta conta." />
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
