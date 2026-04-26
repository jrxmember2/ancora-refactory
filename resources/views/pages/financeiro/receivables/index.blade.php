@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

<x-ancora.section-header title="Contas a Receber" subtitle="Controle completo de recebimentos, baixas, parcelamentos, recibos, renegociacoes e anexos financeiros.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.receivables.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova conta</a>
        <x-financeiro.export-actions scope="receivables" />
    </div>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por codigo, titulo ou cliente..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <select name="client_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os clientes</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->display_name }}</option>
            @endforeach
        </select>
        <select name="condominium_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os condominios</option>
            @foreach($condominiums as $condominium)
                <option value="{{ $condominium->id }}" @selected((string) ($filters['condominium_id'] ?? '') === (string) $condominium->id)>{{ $condominium->name }}</option>
            @endforeach
        </select>
        <select name="billing_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os tipos</option>
            @foreach($billingTypes as $key => $label)
                <option value="{{ $key }}" @selected(($filters['billing_type'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os status</option>
            @foreach($receivableStatuses as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="category_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todas as categorias</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="account_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todas as contas</option>
            @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) ($filters['account_id'] ?? '') === (string) $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
        <select name="responsible_user_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <option value="">Todos os responsaveis</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((string) ($filters['responsible_user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800"><input type="checkbox" name="overdue_only" value="1" @checked(!empty($filters['overdue_only']))> Vencidas</label>
        <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-800"><input type="checkbox" name="without_pdf" value="1" @checked(!empty($filters['without_pdf']))> Sem PDF</label>
        <div class="flex gap-3 xl:col-span-5">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('financeiro.receivables.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Valor total" :value="$money($summary['total'])" hint="Total filtrado." icon="fa-solid fa-sack-dollar" />
    <x-ancora.stat-card label="Recebido" :value="$money($summary['recebido'])" hint="Baixas ja registradas." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Pendente" :value="$money($summary['pendente'])" hint="Saldo em aberto." icon="fa-solid fa-hourglass-half" />
    <x-ancora.stat-card label="Vencido" :value="$money($summary['vencido'])" hint="Titulos atrasados." icon="fa-solid fa-triangle-exclamation" />
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-file-invoice-dollar" title="Sem contas a receber" subtitle="Nenhuma conta a receber foi encontrada com os filtros aplicados." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3"><input type="checkbox" data-select-all class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></th>
                        <th class="px-4 py-3"><x-ancora.sort-link field="code" label="Codigo" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-4 py-3"><x-ancora.sort-link field="title" label="Titulo" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Condominio</th>
                        <th class="px-4 py-3"><x-ancora.sort-link field="due_date" label="Vencimento" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-4 py-3"><x-ancora.sort-link field="amount" label="Valor" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-4 py-3">Saldo</th>
                        <th class="px-4 py-3"><x-ancora.sort-link field="status" label="Status" :sort="$sortState['sort']" :direction="$sortState['direction']" /></th>
                        <th class="px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-4 py-3"><input type="checkbox" name="selected[]" value="{{ $item->id }}" data-select-item class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"></td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item->code ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->title }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $billingTypes[$item->billing_type] ?? ($item->billing_type ?: 'Sem tipo') }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->client?->display_name ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $item->condominium?->name ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ optional($item->due_date)->format('d/m/Y') ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $money($item->final_amount) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $money((float) $item->final_amount - (float) $item->received_amount) }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $receivableStatuses[$item->status] ?? $item->status }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('financeiro.receivables.show', $item) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Visualizar</a>
                                    <a href="{{ route('financeiro.receivables.edit', $item) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Editar</a>
                                    <form method="post" action="{{ route('financeiro.receivables.duplicate', $item) }}">@csrf<button class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium dark:border-gray-700">Duplicar</button></form>
                                    <a href="{{ route('financeiro.receivables.receipt', $item) }}" class="rounded-xl bg-brand-500 px-3 py-2 text-xs font-medium text-white">Recibo</a>
                                </div>
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
