@extends('layouts.app')

@section('content')
@php
    $isEdit = $mode === 'edit';
    $action = $isEdit ? route('financeiro.reimbursements.update', $item) : route('financeiro.reimbursements.store');
@endphp

<x-ancora.section-header :title="$title" subtitle="Registre valores adiantados pelo escritorio, reembolsos recebidos e pendencias futuras.">
    <a href="{{ route('financeiro.reimbursements.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista</a>
</x-ancora.section-header>

<form method="post" action="{{ $action }}" class="space-y-6">
    @csrf
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
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
                <span>Processo</span>
                <select name="process_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <option value="">Selecione</option>
                    @foreach($processes as $process)
                        <option value="{{ $process->id }}" @selected((string) old('process_id', $item->process_id ?? '') === (string) $process->id)>{{ $process->process_number ?: '#' . $process->id }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Tipo</span>
                <input type="text" name="type" value="{{ old('type', $item->type ?? '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Status</span>
                <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($reimbursementStatuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $item->status ?? 'pendente') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Valor</span>
                <input type="text" name="amount" value="{{ old('amount', isset($item) ? number_format((float) $item->amount, 2, ',', '.') : '') }}" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Pago pelo escritorio</span>
                <input type="text" name="paid_by_office_amount" value="{{ old('paid_by_office_amount', isset($item) ? number_format((float) $item->paid_by_office_amount, 2, ',', '.') : '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Reembolsado</span>
                <input type="text" name="reimbursed_amount" value="{{ old('reimbursed_amount', isset($item) ? number_format((float) $item->reimbursed_amount, 2, ',', '.') : '') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Vencimento</span>
                <input type="date" name="due_date" value="{{ old('due_date', optional($item->due_date ?? null)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </label>
            <label class="space-y-2 text-sm text-gray-700 dark:text-gray-200">
                <span>Data do reembolso</span>
                <input type="date" name="reimbursed_at" value="{{ old('reimbursed_at', optional($item->reimbursed_at ?? null)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
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
        @if($isEdit && $item)
            <button type="submit" formaction="{{ route('financeiro.reimbursements.delete', $item) }}" formnovalidate class="rounded-xl border border-rose-200 px-5 py-3 text-sm font-medium text-rose-600 dark:border-rose-900 dark:text-rose-300">Excluir</button>
        @endif
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $isEdit ? 'Salvar alteracoes' : 'Salvar reembolso' }}</button>
    </div>
</form>
@endsection
