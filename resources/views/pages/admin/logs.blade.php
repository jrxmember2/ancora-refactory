@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Logs e auditoria" subtitle="Rastreabilidade central do novo core Laravel com filtros por usuário, ação, módulo e período." />

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar nos logs..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 xl:col-span-2 dark:border-gray-700 dark:text-gray-100">
        <select name="user_email" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os usuários</option>
            @foreach($filterOptions['users'] ?? [] as $email)
                <option value="{{ $email }}" @selected(($filters['user_email'] ?? '') === $email)>{{ $email }}</option>
            @endforeach
        </select>
        <select name="entity_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todos os módulos</option>
            @foreach($filterOptions['entityTypes'] ?? [] as $type)
                <option value="{{ $type['value'] }}" @selected(($filters['entity_type'] ?? '') === $type['value'])>{{ $type['label'] }}</option>
            @endforeach
        </select>
        <select name="action" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 xl:col-span-2 dark:border-gray-700 dark:text-gray-100">
            <option value="">Todas as ações</option>
            @foreach($filterOptions['actions'] ?? [] as $action)
                <option value="{{ $action['value'] }}" @selected(($filters['action'] ?? '') === $action['value'])>{{ $action['label'] }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-gray-800 dark:border-gray-700 dark:text-gray-100">
        <div class="flex gap-3 xl:col-span-4">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('logs.index') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-clock-rotate-left" title="Sem logs" subtitle="Nenhum evento foi encontrado para os filtros atuais." /></div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4"><x-ancora.sort-link field="created_at" label="Quando" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="user" label="Usuário" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="entity_type" label="Módulo" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="action" label="Ação" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="details" label="Detalhes" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ optional($item->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $item->user_email }}</div><div class="text-xs text-gray-500 dark:text-gray-400">ID {{ $item->user_id ?: '—' }}</div></td>
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $auditPresenter::moduleLabel($item->entity_type) }} @if($item->entity_id)<span class="text-xs text-gray-500">#{{ $item->entity_id }}</span>@endif</td>
                            <td class="px-6 py-4"><span title="{{ $item->action }}" class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $auditPresenter::actionLabel($item->action) }}</span></td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $auditPresenter::detailsForDisplay($item) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    @endif
</div>
@endsection
