@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Busca inteligente" subtitle="Pesquisa rápida entre usuários, propostas e clientes." />
<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="flex gap-3">
        <input type="search" name="q" value="{{ $term }}" placeholder="Digite nome, e-mail, código da proposta..." class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">Buscar</button>
    </form>
</div>
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><h3 class="text-base font-semibold text-gray-900 dark:text-white">Usuários</h3><div class="mt-4 space-y-3">@forelse($results['users'] as $item)<div><div class="font-medium text-gray-900 dark:text-white">{{ $item->name }}</div><div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->email }}</div></div>@empty<x-ancora.empty-state icon="fa-solid fa-user-group" title="Sem resultados" subtitle="Nada encontrado em usuários." />@endforelse</div></div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><h3 class="text-base font-semibold text-gray-900 dark:text-white">Propostas</h3><div class="mt-4 space-y-3">@forelse($results['proposals'] as $item)<div><div class="font-medium text-gray-900 dark:text-white">{{ $item->proposal_code }}</div><div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->client_name }}</div></div>@empty<x-ancora.empty-state icon="fa-solid fa-file-lines" title="Sem resultados" subtitle="Nada encontrado em propostas." />@endforelse</div></div>
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><h3 class="text-base font-semibold text-gray-900 dark:text-white">Clientes</h3><div class="mt-4 space-y-3">@forelse($results['clients'] as $item)<div><div class="font-medium text-gray-900 dark:text-white">{{ $item->display_name }}</div><div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->cpf_cnpj ?: $item->role_tag }}</div></div>@empty<x-ancora.empty-state icon="fa-solid fa-address-book" title="Sem resultados" subtitle="Nada encontrado em clientes." />@endforelse</div></div>
</div>
@endsection
