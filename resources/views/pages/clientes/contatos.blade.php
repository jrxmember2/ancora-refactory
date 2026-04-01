@extends('layouts.app')
@section('content')
<x-ancora.section-header title="Parceiros e fornecedores" subtitle="Cadastre síndicos, administradoras, parceiros estratégicos e fornecedores reutilizáveis.">
    <a href="{{ route('clientes.contatos.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo cadastro</a>
</x-ancora.section-header>
@include('pages.clientes.partials.subnav')
<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4">
        <select name="role_tag" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4"><option value="">Todos os papéis</option><option value="sindico" @selected(($filters['role_tag'] ?? '')==='sindico')>Síndico</option><option value="administradora" @selected(($filters['role_tag'] ?? '')==='administradora')>Administradora</option></select>
        <div class="flex gap-3"><button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button><a href="{{ route('clientes.contatos') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium">Limpar</a></div>
    </form>
</div>
<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">@if($items->count()===0)<div class="p-6"><x-ancora.empty-state icon="fa-solid fa-address-card" title="Sem contatos" subtitle="Nenhum contato cadastrado até o momento." /></div>@else<div class="overflow-x-auto"><table class="min-w-full text-left"><thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40"><tr class="text-xs uppercase tracking-[0.16em] text-gray-500"><th class="px-6 py-4">Nome</th><th class="px-6 py-4">Papel</th><th class="px-6 py-4">Documento</th><th class="px-6 py-4"></th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@foreach($items as $item)<tr><td class="px-6 py-4"><div class="font-medium">{{ $item->display_name }}</div><div class="text-xs text-gray-500">{{ $item->legal_name }}</div></td><td class="px-6 py-4">{{ ucfirst($item->role_tag) }}</td><td class="px-6 py-4">{{ $item->cpf_cnpj ?: '—' }}</td><td class="px-6 py-4 text-right"><a href="{{ route('clientes.contatos.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium">Editar</a></td></tr>@endforeach</tbody></table></div><div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>@endif</div>
@endsection
