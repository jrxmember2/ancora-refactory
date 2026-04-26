@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

<x-ancora.section-header title="Fluxo de Caixa" subtitle="Movimentacoes manuais, saldo por conta, previsao financeira e trilha consolidada de entradas e saidas.">
    <x-financeiro.export-actions scope="cash-flow" />
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[2fr,1fr]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <select name="transaction_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Todos os tipos</option>
                    @foreach($transactionTypes as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['transaction_type'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="account_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Todas as contas</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Todas as categorias</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <div class="flex gap-3 xl:col-span-5">
                    <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
                    <a href="{{ route('financeiro.cash-flow.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
            <x-ancora.stat-card label="Saldo real" :value="$money($summary['saldo_real'])" hint="Somatorio atual das contas." icon="fa-solid fa-wallet" />
            <x-ancora.stat-card label="Saldo previsto" :value="$money($summary['saldo_previsto'])" hint="Considera abertos a receber e pagar." icon="fa-solid fa-chart-line" />
            <x-ancora.stat-card label="Receber em aberto" :value="$money($summary['receber_aberto'])" hint="Titulos ainda nao liquidados." icon="fa-solid fa-arrow-trend-up" />
            <x-ancora.stat-card label="Pagar em aberto" :value="$money($summary['pagar_aberto'])" hint="Compromissos pendentes." icon="fa-solid fa-arrow-trend-down" />
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            @if($items->count() === 0)
                <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-money-bill-transfer" title="Sem movimentacoes" subtitle="Nenhum lancamento foi encontrado com os filtros informados." /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                                <th class="px-4 py-3"><x-ancora.sort-link field="date" label="Data" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                                <th class="px-4 py-3"><x-ancora.sort-link field="type" label="Tipo" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                                <th class="px-4 py-3"><x-ancora.sort-link field="account" label="Conta" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                                <th class="px-4 py-3"><x-ancora.sort-link field="category" label="Categoria" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                                <th class="px-4 py-3">Origem</th>
                                <th class="px-4 py-3"><x-ancora.sort-link field="amount" label="Valor" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                                <th class="px-4 py-3"><x-ancora.sort-link field="status" label="Status" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($items as $item)
                                <tr>
                                    <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $item->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ optional($item->transaction_date)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $transactionTypes[$item->transaction_type] ?? $item->transaction_type }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->account?->name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->category?->name ?: '-' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->source ?: '-' }}</td>
                                    <td class="px-4 py-3 {{ in_array($item->transaction_type, ['entrada'], true) ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}">{{ $money($item->amount) }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->reconciliation_status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Novo lancamento</h3>
            <form method="post" action="{{ route('financeiro.cash-flow.store') }}" class="mt-4 space-y-4">
                @csrf
                <select name="transaction_type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($transactionTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select name="account_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Conta principal</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
                <select name="destination_account_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Conta destino (transferencia)</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Categoria</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="cost_center_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Centro de custo</option>
                    @foreach($costCenters as $costCenter)
                        <option value="{{ $costCenter->id }}">{{ $costCenter->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="amount" placeholder="Valor" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <input type="datetime-local" name="transaction_date" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <select name="payment_method" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Forma de pagamento</option>
                    @foreach($paymentMethods as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" name="source" placeholder="Origem" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <input type="text" name="document_number" placeholder="Documento" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <textarea name="description" rows="4" placeholder="Observacao" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700"></textarea>
                <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Registrar movimentacao</button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Saldo por conta</h3>
            <div class="mt-4 space-y-3">
                @foreach($balances as $account)
                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                        <div class="font-medium text-gray-900 dark:text-white">{{ $account['name'] }}</div>
                        <div class="mt-1 text-sm {{ $account['balance'] < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-gray-600 dark:text-gray-300' }}">{{ $money($account['balance']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
