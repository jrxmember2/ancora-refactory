@extends('layouts.app')

@section('content')
@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('financeiro.payables.update', $item) : route('financeiro.payables.store');
@endphp

<x-ancora.section-header :title="$title" subtitle="Cadastre despesas, fornecedores, recorrencias, pagamento e responsabilidades internas.">
    <div class="flex flex-wrap gap-3">
        @if($isEdit && $item)
            <a href="{{ route('financeiro.payables.show', $item) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Visualizar</a>
        @endif
        <a href="{{ route('financeiro.payables.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
    </div>
</x-ancora.section-header>

<form method="post" action="{{ $action }}" class="space-y-6">
    @csrf
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Codigo interno</span>
                <input type="text" name="code" value="{{ old('code', $item->code ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200 xl:col-span-3">
                <span>Titulo</span>
                <input type="text" name="title" value="{{ old('title', $item->title ?? '') }}" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Fornecedor vinculado</span>
                <select name="supplier_entity_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('supplier_entity_id', $item->supplier_entity_id ?? '') === (string) $client->id)>{{ $client->display_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Fornecedor avulso</span>
                <input type="text" name="supplier_name_snapshot" value="{{ old('supplier_name_snapshot', $item->supplier_name_snapshot ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Categoria</span>
                <select name="category_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Centro de custo</span>
                <select name="cost_center_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($costCenters as $costCenter)
                        <option value="{{ $costCenter->id }}" @selected((string) old('cost_center_id', $item->cost_center_id ?? '') === (string) $costCenter->id)>{{ $costCenter->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Conta financeira</span>
                <select name="account_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id', $item->account_id ?? '') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Processo vinculado</span>
                <select name="process_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($processes as $process)
                        <option value="{{ $process->id }}" @selected((string) old('process_id', $item->process_id ?? '') === (string) $process->id)>{{ $process->process_number ?: '#' . $process->id }} · {{ $process->client_name_snapshot }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Valor</span>
                <input type="text" name="amount" value="{{ old('amount', isset($item) ? number_format((float) $item->amount, 2, ',', '.') : '') }}" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Vencimento</span>
                <input type="date" name="due_date" value="{{ old('due_date', optional($item->due_date ?? null)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Competencia</span>
                <input type="date" name="competence_date" value="{{ old('competence_date', optional($item->competence_date ?? null)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Status</span>
                <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($payableStatuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $item->status ?? 'aberto') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Forma de pagamento</span>
                <select name="payment_method" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($paymentMethods as $key => $label)
                        <option value="{{ $key }}" @selected(old('payment_method', $item->payment_method ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Recorrencia</span>
                <select name="recurrence" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($recurrences as $key => $label)
                        <option value="{{ $key }}" @selected(old('recurrence', $item->recurrence ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Responsavel</span>
                <select name="responsible_user_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('responsible_user_id', $item->responsible_user_id ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200 md:col-span-2 xl:col-span-4">
                <span>Observacoes</span>
                <textarea name="notes" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('notes', $item->notes ?? '') }}</textarea>
            </label>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('financeiro.payables.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $isEdit ? 'Salvar alteracoes' : 'Criar conta a pagar' }}</button>
    </div>
</form>
@endsection
