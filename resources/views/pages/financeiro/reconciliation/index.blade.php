@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Conciliacao Bancaria" subtitle="Importe extratos OFX, CSV ou XLSX, revise linhas e concilie contra movimentacoes pendentes do Financeiro 360.">
    <x-financeiro.export-actions scope="statements" />
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,2fr]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Importar extrato</h3>
            <form method="post" action="{{ route('financeiro.reconciliation.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <select name="account_id" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione a conta</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                <input type="file" name="statement_file" accept=".ofx,.csv,.xlsx" required class="block text-sm text-gray-600 dark:text-gray-300">
                <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Analisar extrato</button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Filtros</h3>
            <form method="get" class="mt-4 space-y-4">
                <select name="account_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Todas as contas</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Todos os status</option>
                    <option value="pendente" @selected(($filters['status'] ?? '') === 'pendente')>Pendente</option>
                    <option value="conciliado" @selected(($filters['status'] ?? '') === 'conciliado')>Conciliado</option>
                </select>
                <div class="flex gap-3">
                    <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
                    <a href="{{ route('financeiro.reconciliation.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        @if($items->count() === 0)
            <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-building-columns" title="Sem extratos conciliaveis" subtitle="Importe um extrato bancario para iniciar a conciliacao." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                        <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Conta</th>
                            <th class="px-4 py-3">Descricao</th>
                            <th class="px-4 py-3">Documento</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Conciliar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($items as $item)
                            <tr>
                                <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $item->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                                <td class="px-4 py-3">{{ optional($item->statement_date)->format('d/m/Y H:i') ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $item->account?->name ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $item->description ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $item->document_number ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $item->direction }}</td>
                                <td class="px-4 py-3">{{ 'R$ ' . number_format((float) $item->amount, 2, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $item->is_reconciled ? 'Conciliado' : 'Pendente' }}</td>
                                <td class="px-4 py-3">
                                    @if($item->is_reconciled)
                                        <span class="text-xs text-emerald-600 dark:text-emerald-300">Ja conciliado</span>
                                    @else
                                        <form method="post" action="{{ route('financeiro.reconciliation.conciliate', $item) }}" class="space-y-2">
                                            @csrf
                                            <select name="transaction_id" class="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700">
                                                <option value="">Selecione</option>
                                                @foreach($pendingTransactions as $transaction)
                                                    <option value="{{ $transaction->id }}">{{ $transaction->code }} - {{ optional($transaction->transaction_date)->format('d/m/Y') }} - R$ {{ number_format((float) $transaction->amount, 2, ',', '.') }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" name="notes" placeholder="Observacao" class="h-10 w-full rounded-xl border border-gray-300 bg-transparent px-3 text-xs dark:border-gray-700">
                                            <button class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Conciliar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
