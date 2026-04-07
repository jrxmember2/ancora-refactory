@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Cobranças" subtitle="Lista de OS com filtros por condomínio, etapa, situação e faturamento.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.dashboard') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Dashboard</a>
        <a href="{{ route('cobrancas.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova OS</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 xl:grid-cols-6">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="OS, devedor, processo, unidade..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 xl:col-span-2 dark:border-gray-700 dark:text-white">
        <select name="condominium_id" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Condomínio</option>
            @foreach($filterOptions['condominiums'] as $item)
                <option value="{{ $item->id }}" @selected((int) ($filters['condominium_id'] ?? 0) === (int) $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <select name="charge_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Tipo</option>
            @foreach($filterOptions['chargeTypes'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['charge_type'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="workflow_stage" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Etapa</option>
            @foreach($filterOptions['workflowStages'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['workflow_stage'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="situation" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Situação</option>
            @foreach($filterOptions['situations'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['situation'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="billing_status" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
            <option value="">Faturamento</option>
            @foreach($filterOptions['billingStatuses'] as $key => $label)
                <option value="{{ $key }}" @selected(($filters['billing_status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-white">
        <div class="xl:col-span-2 flex flex-wrap gap-3">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('cobrancas.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6">
            <x-ancora.empty-state icon="fa-solid fa-money-bill-wave" title="Nenhuma OS encontrada" subtitle="Ajuste os filtros ou cadastre uma nova cobrança." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">OS</th>
                        <th class="px-6 py-4">Condomínio / unidade</th>
                        <th class="px-6 py-4">Devedor</th>
                        <th class="px-6 py-4">Etapa</th>
                        <th class="px-6 py-4">Situação</th>
                        <th class="px-6 py-4">Acordo</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $item->os_number }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($item->created_at)->format('d/m/Y') }}</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item->charge_type === 'judicial' ? 'Judicial' : 'Extrajudicial' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->condominium?->name ?? 'Condomínio não vinculado' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->block?->name ? $item->block->name.' · ' : '' }}Unidade {{ $item->unit?->unit_number ?? '—' }}</div>
                                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item->quotas_count }} quota(s) · {{ $item->attachments_count }} anexo(s)</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item->debtor_name_snapshot }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->debtor_document_snapshot ?: 'Sem documento' }}</div>
                            </td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $filterOptions['workflowStages'][$item->workflow_stage] ?? $item->workflow_stage }}</td>
                            <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $filterOptions['situations'][$item->situation] ?? $item->situation }}</td>
                            <td class="px-6 py-4 align-top">
                                <div class="text-sm text-gray-700 dark:text-gray-200">{{ $item->agreement_total ? 'R$ '.number_format((float) $item->agreement_total, 2, ',', '.') : 'Não definido' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $filterOptions['billingStatuses'][$item->billing_status] ?? $item->billing_status }}</div>
                            </td>
                            <td class="px-6 py-4 align-top">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('cobrancas.show', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Abrir</a>
                                    <a href="{{ route('cobrancas.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                    <form method="post" action="{{ route('cobrancas.delete', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button onclick="return confirm('Excluir esta OS de cobrança?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            {{ $items->links() }}
        </div>
    @endif
</div>
@endsection
