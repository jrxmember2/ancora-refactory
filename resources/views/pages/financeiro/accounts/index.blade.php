@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
@endphp

<x-ancora.section-header title="Bancos e Contas" subtitle="Cadastro central de contas correntes, bancos digitais, caixas, carteiras e contas de operacao financeira.">
    <x-financeiro.export-actions scope="accounts" :allow-selection="false" />
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr,1.9fr]">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Nova conta financeira</h3>
        <form method="post" action="{{ route('financeiro.accounts.store') }}" class="mt-4 space-y-4">
            @csrf
            <input type="text" name="code" placeholder="Codigo" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <input type="text" name="name" placeholder="Nome da conta" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <input type="text" name="bank_name" placeholder="Banco" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="agency" placeholder="Agencia" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <input type="text" name="account_number" placeholder="Conta" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="account_digit" placeholder="Digito" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <select name="account_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($accountTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <input type="text" name="pix_key" placeholder="Chave Pix" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <input type="text" name="account_holder" placeholder="Titular" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="opening_balance" placeholder="Saldo inicial" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                <input type="text" name="credit_limit" placeholder="Limite" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </div>
            <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="is_primary" value="1"> Conta principal</label>
            <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="is_active" value="1" checked> Conta ativa</label>
            <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar conta</button>
        </form>
    </div>

    <div class="space-y-4">
        @forelse($items as $item)
            <details class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $item->name }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->bank_name ?: 'Sem banco informado' }} - {{ $accountTypes[$item->account_type] ?? $item->account_type }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-medium {{ $item->is_primary ? 'text-brand-500' : 'text-gray-500 dark:text-gray-400' }}">{{ $item->is_primary ? 'Principal' : 'Secundaria' }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->is_active ? 'Ativa' : 'Inativa' }}</div>
                    </div>
                </summary>
                <div class="mt-5 grid grid-cols-1 gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div><span class="text-gray-500 dark:text-gray-400">Codigo</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->code ?: '-' }}</div></div>
                    <div><span class="text-gray-500 dark:text-gray-400">Agencia</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->agency ?: '-' }}</div></div>
                    <div><span class="text-gray-500 dark:text-gray-400">Conta</span><div class="font-medium text-gray-900 dark:text-white">{{ trim(($item->account_number ?: '-') . ' ' . ($item->account_digit ?: '')) }}</div></div>
                    <div><span class="text-gray-500 dark:text-gray-400">Pix</span><div class="font-medium text-gray-900 dark:text-white">{{ $item->pix_key ?: '-' }}</div></div>
                    <div><span class="text-gray-500 dark:text-gray-400">Saldo inicial</span><div class="font-medium text-gray-900 dark:text-white">{{ $money($item->opening_balance) }}</div></div>
                    <div><span class="text-gray-500 dark:text-gray-400">Limite</span><div class="font-medium text-gray-900 dark:text-white">{{ $money($item->credit_limit) }}</div></div>
                </div>
                <form method="post" action="{{ route('financeiro.accounts.update', $item) }}" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @csrf
                    <input type="text" name="code" value="{{ $item->code }}" placeholder="Codigo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="name" value="{{ $item->name }}" required placeholder="Nome" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="bank_name" value="{{ $item->bank_name }}" placeholder="Banco" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <select name="account_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                        @foreach($accountTypes as $key => $label)
                            <option value="{{ $key }}" @selected($item->account_type === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="agency" value="{{ $item->agency }}" placeholder="Agencia" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="account_number" value="{{ $item->account_number }}" placeholder="Conta" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="account_digit" value="{{ $item->account_digit }}" placeholder="Digito" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="pix_key" value="{{ $item->pix_key }}" placeholder="Chave Pix" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="account_holder" value="{{ $item->account_holder }}" placeholder="Titular" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="opening_balance" value="{{ number_format((float) $item->opening_balance, 2, ',', '.') }}" placeholder="Saldo inicial" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="credit_limit" value="{{ number_format((float) $item->credit_limit, 2, ',', '.') }}" placeholder="Limite" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <div class="flex items-center gap-4 text-sm text-gray-700 dark:text-gray-200">
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_primary" value="1" @checked($item->is_primary)> Principal</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Ativa</label>
                    </div>
                    <div class="flex justify-end gap-3 md:col-span-2">
                        <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar</button>
                    </div>
                </form>
                <form method="post" action="{{ route('financeiro.accounts.delete', $item) }}" class="mt-3 text-right">
                    @csrf
                    <button class="rounded-xl border border-rose-200 px-4 py-3 text-sm font-medium text-rose-600 dark:border-rose-900 dark:text-rose-300">Excluir conta</button>
                </form>
            </details>
        @empty
            <x-ancora.empty-state icon="fa-solid fa-building-columns" title="Sem contas financeiras" subtitle="Cadastre ao menos uma conta para controlar bancos, caixa e conciliacao." />
        @endforelse
    </div>
</div>
@endsection
