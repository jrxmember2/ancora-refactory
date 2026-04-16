@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Logs e auditoria" subtitle="Rastreabilidade central do novo core Laravel." />
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-clock-rotate-left" title="Sem logs" subtitle="Nenhum evento foi registrado ainda." /></div>
    @else
        <div class="overflow-x-auto"><table class="min-w-full text-left"><thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40"><tr class="text-xs uppercase tracking-[0.16em] text-gray-500"><th class="px-6 py-4"><x-ancora.sort-link field="created_at" label="Quando" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th><th class="px-6 py-4"><x-ancora.sort-link field="user" label="Usuário" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th><th class="px-6 py-4"><x-ancora.sort-link field="action" label="Ação" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th><th class="px-6 py-4"><x-ancora.sort-link field="details" label="Detalhes" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@foreach($items as $item)<tr><td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ optional($item->created_at)->format('d/m/Y H:i') }}</td><td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $item->user_email }}</div></td><td class="px-6 py-4"><span class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $item->action }}</span></td><td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $item->details }}</td></tr>@endforeach</tbody></table></div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    @endif
</div>
@endsection
