@extends('layouts.app')

@section('content')
@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('financeiro.receivables.update', $item) : route('financeiro.receivables.store');
@endphp

<x-ancora.section-header :title="$title" subtitle="Cadastre recebiveis manuais, vinculados a contratos, condominios, unidades ou processos.">
    <div class="flex flex-wrap gap-3">
        @if($isEdit && $item)
            <a href="{{ route('financeiro.receivables.show', $item) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Visualizar</a>
        @endif
        <a href="{{ route('financeiro.receivables.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
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
                <span>Referencia</span>
                <input type="text" name="reference" value="{{ old('reference', $item->reference ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Tipo de cobranca</span>
                <select name="billing_type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($billingTypes as $key => $label)
                        <option value="{{ $key }}" @selected(old('billing_type', $item->billing_type ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Cliente</span>
                <select name="client_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('client_id', $item->client_id ?? '') === (string) $client->id)>{{ $client->display_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Condominio</span>
                <select name="condominium_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($condominiums as $condominium)
                        <option value="{{ $condominium->id }}" @selected((string) old('condominium_id', $item->condominium_id ?? '') === (string) $condominium->id)>{{ $condominium->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Unidade</span>
                <select name="unit_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_id', $item->unit_id ?? '') === (string) $unit->id)>{{ $unit->condominium?->name }} · {{ $unit->block?->name ? $unit->block->name . ' · ' : '' }}{{ $unit->unit_number }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Contrato vinculado</span>
                <select name="contract_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected((string) old('contract_id', $item->contract_id ?? '') === (string) $contract->id)>{{ $contract->code ?: '#' . $contract->id }} · {{ $contract->title }}</option>
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
                <span>Valor original</span>
                <input type="text" name="original_amount" value="{{ old('original_amount', isset($item) ? number_format((float) $item->original_amount, 2, ',', '.') : '') }}" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Juros</span>
                <input type="text" name="interest_amount" value="{{ old('interest_amount', isset($item) ? number_format((float) $item->interest_amount, 2, ',', '.') : '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Multa</span>
                <input type="text" name="penalty_amount" value="{{ old('penalty_amount', isset($item) ? number_format((float) $item->penalty_amount, 2, ',', '.') : '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Correcao</span>
                <input type="text" name="correction_amount" value="{{ old('correction_amount', isset($item) ? number_format((float) $item->correction_amount, 2, ',', '.') : '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Desconto</span>
                <input type="text" name="discount_amount" value="{{ old('discount_amount', isset($item) ? number_format((float) $item->discount_amount, 2, ',', '.') : '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
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
                <span>Forma de pagamento</span>
                <select name="payment_method" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($paymentMethods as $key => $label)
                        <option value="{{ $key }}" @selected(old('payment_method', $item->payment_method ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Status</span>
                <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($receivableStatuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $item->status ?? 'aberto') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Etapa de cobranca</span>
                <select name="collection_stage" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($collectionStages as $key => $label)
                        <option value="{{ $key }}" @selected(old('collection_stage', $item->collection_stage ?? '') === $key)>{{ $label }}</option>
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
            <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                <input type="checkbox" name="generate_collection" value="1" @checked(old('generate_collection', $item->generate_collection ?? false))>
                Gerar cobranca automatica
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200 md:col-span-2 xl:col-span-4">
                <span>Observacoes</span>
                <textarea name="notes" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('notes', $item->notes ?? '') }}</textarea>
            </label>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('financeiro.receivables.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $isEdit ? 'Salvar alteracoes' : 'Criar conta a receber' }}</button>
    </div>
</form>
@endsection
