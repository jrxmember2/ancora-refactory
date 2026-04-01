@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Unidades" subtitle="Vínculo entre condomínio, proprietário e locatário." />
<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-door-closed" title="Sem unidades" subtitle="Nenhuma unidade foi cadastrada até o momento." /></div>
    @else
        <div class="overflow-x-auto"><table class="min-w-full text-left"><thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40"><tr class="text-xs uppercase tracking-[0.16em] text-gray-500"><th class="px-6 py-4">Unidade</th><th class="px-6 py-4">Condomínio</th><th class="px-6 py-4">Proprietário</th><th class="px-6 py-4">Locatário</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@foreach($items as $item)<tr><td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $item->unit_number }}</div></td><td class="px-6 py-4">{{ $item->condominium_name ?: '—' }}</td><td class="px-6 py-4">{{ $item->owner_name ?: '—' }}</td><td class="px-6 py-4">{{ $item->tenant_name ?: '—' }}</td></tr>@endforeach</tbody></table></div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    @endif
</div>
@endsection
