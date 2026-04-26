@extends('layouts.app')

@section('content')
@php
    $value = fn ($key) => old($key, $settings[$key] ?? $defaults[$key] ?? '');
@endphp

<x-ancora.section-header title="Configuracoes Financeiras" subtitle="Padroes de juros, multa, numeracao, conta preferencial e comportamento do modulo Financeiro 360.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('financeiro.dashboard') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Dashboard</a>
    </div>
</x-ancora.section-header>

<form method="post" action="{{ route('financeiro.settings.save') }}" class="space-y-6">
    @csrf
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Juros padrao (%)</span>
                <input type="text" name="default_interest_percent" value="{{ $value('default_interest_percent') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Multa padrao (%)</span>
                <input type="text" name="default_penalty_percent" value="{{ $value('default_penalty_percent') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Conta padrao</span>
                <select name="default_account_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) $value('default_account_id') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Dias de alerta</span>
                <input type="number" min="1" max="365" name="alert_days" value="{{ $value('alert_days') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Prefixo de lancamentos</span>
                <input type="text" name="entry_prefix" value="{{ $value('entry_prefix') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Dia padrao de faturamento</span>
                <input type="number" min="1" max="31" name="billing_due_day" value="{{ $value('billing_due_day') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Cidade padrao</span>
                <input type="text" name="default_city" value="{{ $value('default_city') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Estado padrao</span>
                <input type="text" name="default_state" value="{{ $value('default_state') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Status padrao de contas a receber</span>
                <select name="default_receivable_status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($receivableStatuses as $key => $label)
                        <option value="{{ $key }}" @selected((string) $value('default_receivable_status') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Status padrao de contas a pagar</span>
                <select name="default_payable_status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($payableStatuses as $key => $label)
                        <option value="{{ $key }}" @selected((string) $value('default_payable_status') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                <input type="checkbox" name="auto_numbering" value="1" @checked((string) $value('auto_numbering') === '1')>
                Numeracao automatica ativa
            </label>
        </div>
    </div>

    <div class="flex justify-end">
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar configuracoes</button>
    </div>
</form>
@endsection
