@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Clientes avulsos" subtitle="Cadastros PF e PJ do escritório, fora da estrutura condominial.">
    <a href="{{ route('clientes.avulsos.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo cliente avulso</a>
</x-ancora.section-header>
@include('pages.clientes.partials.subnav')
<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por nome ou documento..." class="h-11 rounded-xl border border-gray-300 bg-transparent px-4">
        <select name="entity_type" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4"><option value="">PF e PJ</option><option value="pf" @selected(($filters['entity_type'] ?? '')==='pf')>Pessoa física</option><option value="pj" @selected(($filters['entity_type'] ?? '')==='pj')>Pessoa jurídica</option></select>
        <select name="role_tag" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4"><option value="">Todos os perfis/papéis</option>@foreach(($entityRoles ?? collect()) as $role)<option value="{{ $role->name }}" @selected(($filters['role_tag'] ?? '')===$role->name)>{{ $role->name }}</option>@endforeach</select>
        <select name="is_active" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4"><option value="">Ativos e inativos</option><option value="1" @selected(($filters['is_active'] ?? '')==='1')>Ativos</option><option value="0" @selected(($filters['is_active'] ?? '')==='0')>Inativos</option></select>
        <div class="flex gap-3"><button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button><a href="{{ route('clientes.avulsos') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium">Limpar</a></div>
    </form>
</div>
<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6"><x-ancora.empty-state icon="fa-solid fa-user-xmark" title="Nenhum cliente avulso encontrado" subtitle="Ajuste os filtros ou cadastre novos registros." /></div>
    @else
        <div class="overflow-x-auto"><table class="min-w-full text-left"><thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40"><tr class="text-xs uppercase tracking-[0.16em] text-gray-500"><th class="px-6 py-4">Nome</th><th class="px-6 py-4">Perfil / papel</th><th class="px-6 py-4">Tipo</th><th class="px-6 py-4">Documento</th><th class="px-6 py-4">Status</th><th class="px-6 py-4"></th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800">@foreach($items as $item)<tr><td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $item->display_name }}</div><div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->legal_name }}</div></td><td class="px-6 py-4">{{ $item->role_tag ?: '—' }}</td><td class="px-6 py-4">{{ strtoupper($item->entity_type) }}</td><td class="px-6 py-4">{{ $item->cpf_cnpj ?: '—' }}</td><td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs {{ $item->is_active ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' : 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300' }}">{{ $item->is_active ? 'Ativo' : 'Inativo' }}</span></td><td class="px-6 py-4 text-right"><a href="{{ route('clientes.avulsos.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium">Editar</a></td></tr>@endforeach</tbody></table></div>
        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    @endif
</div>
@endsection
