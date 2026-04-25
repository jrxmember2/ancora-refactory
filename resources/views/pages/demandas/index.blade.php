@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

@section('content')
<x-ancora.section-header title="Demandas" subtitle="Solicitacoes abertas pelo Portal do Cliente e tambem cadastradas internamente pelo escritorio.">
    <a href="{{ route('demandas.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova demanda</a>
    <a href="{{ route('demandas.dashboard') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Dashboard</a>
    <a href="{{ route('demandas.kanban') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Kanban</a>
    <a href="{{ route('clientes.portal-users.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Usuarios do portal</a>
</x-ancora.section-header>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 xl:grid-cols-7">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Protocolo, assunto, cliente..." class="{{ $inputClass }} xl:col-span-2">
        <select name="status" class="{{ $inputClass }}">
            <option value="">Status</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="priority" class="{{ $inputClass }}">
            <option value="">Prioridade</option>
            @foreach($priorityLabels as $key => $label)
                <option value="{{ $key }}" @selected(($filters['priority'] ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="demand_tag_id" class="{{ $inputClass }}">
            <option value="">Tag</option>
            @foreach($demandTags as $tag)
                <option value="{{ $tag->id }}" @selected((int) ($filters['demand_tag_id'] ?? 0) === (int) $tag->id)>{{ $tag->name }}</option>
            @endforeach
        </select>
        <select name="client_condominium_id" class="{{ $inputClass }}">
            <option value="">Condominio</option>
            @foreach($condominiums as $condominium)
                <option value="{{ $condominium->id }}" @selected((int) ($filters['client_condominium_id'] ?? 0) === (int) $condominium->id)>{{ $condominium->name }}</option>
            @endforeach
        </select>
        <select name="assigned_user_id" class="{{ $inputClass }}">
            <option value="">Responsavel</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) ($filters['assigned_user_id'] ?? 0) === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <div class="xl:col-span-7 flex flex-wrap gap-3">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('demandas.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                    <th class="px-6 py-4">Demanda</th>
                    <th class="px-6 py-4">Cliente</th>
                    <th class="px-6 py-4">Tag / SLA</th>
                    <th class="px-6 py-4">Responsavel</th>
                    <th class="px-6 py-4">Atualizacao</th>
                    <th class="px-6 py-4 text-right">Acoes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($items as $item)
                    <tr>
                        <td class="px-6 py-4 align-top">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $item->protocol }}</div>
                            <div class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ $item->subject }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $item->category?->name ?: 'Sem categoria' }} - {{ $priorityLabels[$item->priority] ?? $item->priority }} - {{ $item->origin === 'portal' ? 'Portal do cliente' : 'Interna' }}</div>
                        </td>
                        <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $item->clientName() }}</td>
                        <td class="px-6 py-4 align-top">
                            @if($item->tag)
                                <span class="rounded-full px-3 py-1 text-xs font-semibold text-white" style="background-color: {{ $item->tag->color_hex }}">{{ $item->tag->name }}</span>
                            @else
                                <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ $statusLabels[$item->status] ?? $item->status }}</span>
                            @endif
                            <div class="mt-2 text-xs {{ $item->slaStatus() === 'overdue' ? 'text-error-600 dark:text-error-300' : ($item->slaStatus() === 'at_risk' ? 'text-warning-600 dark:text-warning-300' : 'text-gray-500') }}">
                                {{ $item->slaStatusLabel() }}{{ $item->sla_due_at ? ' - '.$item->sla_due_at->format('d/m/Y H:i') : '' }}
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $item->assignee?->name ?: 'Nao atribuido' }}</td>
                        <td class="px-6 py-4 align-top text-sm text-gray-500">{{ $item->updated_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-4 align-top text-right"><a href="{{ route('demandas.show', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6"><x-ancora.empty-state icon="fa-solid fa-inbox" title="Sem demandas" subtitle="As demandas abertas no portal e as cadastradas internamente aparecerao aqui." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
</div>
@endsection
