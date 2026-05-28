@extends('layouts.app')

@section('content')
@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('financeiro.receivables.update', $item) : route('financeiro.receivables.store');
    $contractSelected = (string) old('contract_id', $item->contract_id ?? '') !== '';
    $importContractDefault = old('import_contract_schedule', $contractSelected && !$isEdit ? '1' : '0');
@endphp

<x-ancora.section-header :title="$title" subtitle="Cadastre recebiveis manuais, recorrentes ou importados do financeiro do contrato.">
    <div class="flex flex-wrap gap-3">
        @if($isEdit && $item)
            <a href="{{ route('financeiro.receivables.show', $item) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Visualizar</a>
        @endif
        <a href="{{ route('financeiro.receivables.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
    </div>
</x-ancora.section-header>

<form method="post" action="{{ $action }}" class="space-y-6" id="receivable-form">
    @csrf

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados do recebivel</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use um titulo avulso, uma serie manual ou importe a agenda financeira do contrato vinculado.</p>
            </div>
            @if(!$isEdit)
                <label id="contract-import-wrapper" class="flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200 {{ $contractSelected ? '' : 'hidden' }}">
                    <input type="hidden" name="import_contract_schedule" value="0">
                    <input type="checkbox" name="import_contract_schedule" id="receivable-import-contract" value="1" @checked((string) $importContractDefault === '1')>
                    <span>
                        <span class="block font-medium">Usar financeiro do contrato</span>
                        <span class="mt-1 block text-xs text-brand-700/80 dark:text-brand-200/80">Ao salvar, o sistema cria as recorrencias e parcelas com base na agenda do contrato, respeitando duplicidades ja existentes.</span>
                    </span>
                </label>
            @endif
        </div>

        <div id="contract-import-help" class="mb-5 hidden rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 text-sm text-brand-800 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">
            O contrato selecionado esta alimentando os dados financeiros deste cadastro. Se quiser um titulo avulso, desmarque a opcao acima e ajuste os campos manualmente.
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Codigo interno</span>
                <input type="text" id="receivable-code" name="code" value="{{ old('code', $item->code ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200 xl:col-span-3">
                <span>Titulo</span>
                <input type="text" id="receivable-title" name="title" value="{{ old('title', $item->title ?? '') }}" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Referencia</span>
                <input type="text" id="receivable-reference" name="reference" value="{{ old('reference', $item->reference ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Tipo de cobranca</span>
                <select id="receivable-billing-type" name="billing_type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($billingTypes as $key => $label)
                        <option value="{{ $key }}" @selected(old('billing_type', $item->billing_type ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Cliente</span>
                <select id="receivable-client-id" name="client_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected((string) old('client_id', $item->client_id ?? '') === (string) $client->id)>{{ $client->display_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Condominio</span>
                <select id="receivable-condominium-id" name="condominium_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($condominiums as $condominium)
                        <option value="{{ $condominium->id }}" @selected((string) old('condominium_id', $item->condominium_id ?? '') === (string) $condominium->id)>{{ $condominium->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Unidade</span>
                <select id="receivable-unit-id" name="unit_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_id', $item->unit_id ?? '') === (string) $unit->id)>{{ $unit->condominium?->name }} · {{ $unit->block?->name ? $unit->block->name . ' · ' : '' }}{{ $unit->unit_number }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Contrato vinculado</span>
                <select id="receivable-contract-id" name="contract_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected((string) old('contract_id', $item->contract_id ?? '') === (string) $contract->id)>{{ $contract->code ?: '#' . $contract->id }} · {{ $contract->title }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Processo vinculado</span>
                <select id="receivable-process-id" name="process_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($processes as $process)
                        <option value="{{ $process->id }}" @selected((string) old('process_id', $item->process_id ?? '') === (string) $process->id)>{{ $process->process_number ?: '#' . $process->id }} · {{ $process->client_name_snapshot }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Categoria</span>
                <select id="receivable-category-id" name="category_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $item->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Centro de custo</span>
                <select id="receivable-cost-center-id" name="cost_center_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($costCenters as $costCenter)
                        <option value="{{ $costCenter->id }}" @selected((string) old('cost_center_id', $item->cost_center_id ?? '') === (string) $costCenter->id)>{{ $costCenter->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Conta financeira</span>
                <select id="receivable-account-id" name="account_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('account_id', $item->account_id ?? '') === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Valor original</span>
                <input type="text" id="receivable-original-amount" name="original_amount" value="{{ old('original_amount', isset($item) ? number_format((float) $item->original_amount, 2, ',', '.') : '') }}" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
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
                <input type="date" id="receivable-due-date" name="due_date" value="{{ old('due_date', optional($item->due_date ?? null)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Competencia</span>
                <input type="date" id="receivable-competence-date" name="competence_date" value="{{ old('competence_date', optional($item->competence_date ?? null)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Forma de pagamento</span>
                <select id="receivable-payment-method" name="payment_method" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($paymentMethods as $key => $label)
                        <option value="{{ $key }}" @selected(old('payment_method', $item->payment_method ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Recorrencia</span>
                <select id="receivable-recurrence" name="recurrence" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Sem recorrencia</option>
                    @foreach($recurrences as $key => $label)
                        <option value="{{ $key }}" @selected(old('recurrence', $item->recurrence ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if(!$isEdit)
                <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                    <span>Quantidade de ocorrencias</span>
                    <input type="number" min="1" max="240" id="receivable-occurrences" name="occurrences" value="{{ old('occurrences', '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="Ex.: 12">
                </label>
                <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                    <span>Repetir ate</span>
                    <input type="date" id="receivable-repeat-until" name="repeat_until" value="{{ old('repeat_until', '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                </label>
            @endif
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
                <select id="receivable-responsible-user-id" name="responsible_user_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
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
                <textarea id="receivable-notes" name="notes" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('notes', $item->notes ?? '') }}</textarea>
            </label>
        </div>

        @if($isEdit && !empty($item?->series_group))
            <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-300">
                Este titulo faz parte de uma serie com {{ (int) ($item->series_total ?: 1) }} item(ns). A edicao atual altera apenas este recebivel.
            </div>
        @endif
    </div>

    <div class="flex justify-end gap-3">
        <a href="{{ route('financeiro.receivables.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $isEdit ? 'Salvar alteracoes' : 'Criar conta a receber' }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const contractSnapshots = @json($contractSnapshots);
    const form = document.querySelector('#receivable-form');
    const contractSelect = document.querySelector('#receivable-contract-id');
    if (!form || !contractSelect) {
        return;
    }

    const importToggle = document.querySelector('#receivable-import-contract');
    const importWrapper = document.querySelector('#contract-import-wrapper');
    const importHelp = document.querySelector('#contract-import-help');
    const titleInput = document.querySelector('#receivable-title');
    const referenceInput = document.querySelector('#receivable-reference');
    const clientSelect = document.querySelector('#receivable-client-id');
    const condominiumSelect = document.querySelector('#receivable-condominium-id');
    const unitSelect = document.querySelector('#receivable-unit-id');
    const processSelect = document.querySelector('#receivable-process-id');
    const categorySelect = document.querySelector('#receivable-category-id');
    const costCenterSelect = document.querySelector('#receivable-cost-center-id');
    const accountSelect = document.querySelector('#receivable-account-id');
    const paymentMethodSelect = document.querySelector('#receivable-payment-method');
    const billingTypeSelect = document.querySelector('#receivable-billing-type');
    const recurrenceSelect = document.querySelector('#receivable-recurrence');
    const responsibleSelect = document.querySelector('#receivable-responsible-user-id');
    const originalAmountInput = document.querySelector('#receivable-original-amount');
    const dueDateInput = document.querySelector('#receivable-due-date');
    const competenceDateInput = document.querySelector('#receivable-competence-date');
    const notesInput = document.querySelector('#receivable-notes');

    function parseDecimal(value) {
        const normalized = String(value || '')
            .replace(/\s/g, '')
            .replace(/[R$r$\u00a0]/g, '')
            .replace(/\./g, '')
            .replace(',', '.')
            .replace(/[^0-9.-]/g, '');
        const number = Number(normalized);

        return Number.isFinite(number) ? number : 0;
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setIfPresent(field, value) {
        if (!field || value === null || value === undefined || value === '') {
            return;
        }

        field.value = String(value);
    }

    function computeFirstDueDate(snapshot) {
        const dueDay = Number(snapshot?.due_day || 0);
        const startDate = snapshot?.start_date || '';
        if (!dueDay || !startDate) {
            return '';
        }

        const start = new Date(`${startDate}T12:00:00`);
        if (Number.isNaN(start.getTime())) {
            return '';
        }

        let year = start.getFullYear();
        let month = start.getMonth();
        let candidate = new Date(year, month, Math.min(dueDay, new Date(year, month + 1, 0).getDate()), 12, 0, 0);

        if (candidate < start) {
            month += 1;
            candidate = new Date(year, month, Math.min(dueDay, new Date(year, month + 1, 0).getDate()), 12, 0, 0);
        }

        const monthValue = String(candidate.getMonth() + 1).padStart(2, '0');
        const dayValue = String(candidate.getDate()).padStart(2, '0');

        return `${candidate.getFullYear()}-${monthValue}-${dayValue}`;
    }

    function applyContractSnapshot() {
        const snapshot = contractSnapshots[String(contractSelect.value || '')];
        const usingContract = Boolean(importToggle && importToggle.checked && snapshot);

        importWrapper?.classList.toggle('hidden', !snapshot);
        importHelp?.classList.toggle('hidden', !usingContract);

        if (!snapshot || !usingContract) {
            return;
        }

        setIfPresent(clientSelect, snapshot.client_id);
        setIfPresent(condominiumSelect, snapshot.condominium_id);
        setIfPresent(unitSelect, snapshot.unit_id);
        setIfPresent(processSelect, snapshot.process_id);
        setIfPresent(categorySelect, snapshot.category_id);
        setIfPresent(costCenterSelect, snapshot.cost_center_id);
        setIfPresent(accountSelect, snapshot.account_id);
        setIfPresent(paymentMethodSelect, snapshot.payment_method);
        setIfPresent(billingTypeSelect, snapshot.receivable_billing_type);
        setIfPresent(recurrenceSelect, snapshot.recurrence || (snapshot.billing_type === 'parcelada' ? 'mensal' : 'unica'));
        setIfPresent(responsibleSelect, snapshot.responsible_user_id);

        if (Number(snapshot.estimated_amount || 0) > 0) {
            originalAmountInput.value = formatMoney(snapshot.estimated_amount);
        }

        const firstDueDate = computeFirstDueDate(snapshot);
        if (firstDueDate) {
            dueDateInput.value = firstDueDate;
            if (!competenceDateInput.value) {
                competenceDateInput.value = firstDueDate;
            }
        }

        if (!referenceInput.value) {
            referenceInput.value = snapshot.recurrence === 'unica' ? 'Parcela unica' : '';
        }

        if (!notesInput.value && snapshot.financial_notes) {
            notesInput.value = snapshot.financial_notes;
        }
    }

    contractSelect.addEventListener('change', function () {
        if (importToggle && contractSelect.value && importToggle.checked === false && importWrapper && !importWrapper.classList.contains('hidden')) {
            importToggle.checked = true;
        }
        applyContractSnapshot();
    });

    importToggle?.addEventListener('change', applyContractSnapshot);

    applyContractSnapshot();
});
</script>
@endpush
