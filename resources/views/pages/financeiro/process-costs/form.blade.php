@extends('layouts.app')

@section('content')
@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('financeiro.process-costs.update', $item) : route('financeiro.process-costs.store');
@endphp

<x-ancora.section-header :title="$title" subtitle="Cadastre custas processuais, vincule a processo, categoria e reembolso associado.">
    <a href="{{ route('financeiro.process-costs.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
</x-ancora.section-header>

<form method="post" action="{{ $action }}" class="space-y-6">
    @csrf
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Processo</span>
                <select name="process_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($processes as $process)
                        <option value="{{ $process->id }}" @selected((string) old('process_id', $item->process_id ?? '') === (string) $process->id)>{{ $process->process_number ?: '#' . $process->id }}</option>
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
                <span>Tipo da custa</span>
                <input type="text" name="cost_type" value="{{ old('cost_type', $item->cost_type ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Status</span>
                <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($processCostStatuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $item->status ?? 'lancado') === $key)>{{ $label }}</option>
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
                <span>Reembolso vinculado</span>
                <select name="reimbursement_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach(\App\Models\FinancialReimbursement::query()->orderByDesc('id')->limit(200)->get() as $reimbursement)
                        <option value="{{ $reimbursement->id }}" @selected((string) old('reimbursement_id', $item->reimbursement_id ?? '') === (string) $reimbursement->id)>{{ $reimbursement->code ?: '#' . $reimbursement->id }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Valor</span>
                <input type="text" name="amount" value="{{ old('amount', isset($item) ? number_format((float) $item->amount, 2, ',', '.') : '') }}" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Reembolsado</span>
                <input type="text" name="reimbursed_amount" value="{{ old('reimbursed_amount', isset($item) ? number_format((float) $item->reimbursed_amount, 2, ',', '.') : '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Data da custa</span>
                <input type="date" name="cost_date" value="{{ old('cost_date', optional($item->cost_date ?? null)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200 md:col-span-2 xl:col-span-4">
                <span>Observacoes</span>
                <textarea name="notes" rows="5" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('notes', $item->notes ?? '') }}</textarea>
            </label>
        </div>
    </div>

    <div class="flex justify-end gap-3">
        @if($isEdit && $item)
            <button type="submit" formaction="{{ route('financeiro.process-costs.delete', $item) }}" formnovalidate class="rounded-xl border border-rose-200 px-5 py-3 text-sm font-medium text-rose-600 dark:border-rose-900 dark:text-rose-300">Excluir</button>
        @endif
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $isEdit ? 'Salvar alteracoes' : 'Salvar custa processual' }}</button>
    </div>
</form>
@endsection
