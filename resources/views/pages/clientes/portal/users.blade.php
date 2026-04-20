@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200';
@endphp

@section('content')
<x-ancora.section-header title="Portal do Cliente" subtitle="Usuários externos com autenticação própria e permissões de acesso ao portal.">
    <button type="button" onclick="document.getElementById('portal-user-create').showModal()" class="{{ $buttonClass }}">Novo usuário do portal</button>
</x-ancora.section-header>
@include('pages.clientes.partials.subnav')

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nome, chave ou e-mail..." class="{{ $inputClass }} md:col-span-2">
        <select name="active" class="{{ $inputClass }}">
            <option value="">Todos</option>
            <option value="1" @selected(($filters['active'] ?? '') === '1')>Ativos</option>
            <option value="0" @selected(($filters['active'] ?? '') === '0')>Inativos</option>
        </select>
        <div class="flex gap-2">
            <button class="{{ $buttonClass }}">Filtrar</button>
            <a href="{{ route('clientes.portal-users.index') }}" class="{{ $softButtonClass }}">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                    <th class="px-6 py-4">Usuário</th>
                    <th class="px-6 py-4">Vínculo</th>
                    <th class="px-6 py-4">Permissões</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($items as $item)
                    <tr>
                        <td class="px-6 py-4 align-top">
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $item->name }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item->login_key }} · {{ $item->email ?: 'sem e-mail' }}</div>
                        </td>
                        <td class="px-6 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                            {{ $item->condominium?->name ?: ($item->entity?->display_name ?: 'Sem vínculo') }}
                            <div class="mt-1 text-xs text-gray-500">{{ $roles[$item->portal_role] ?? $item->portal_role }}</div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex max-w-md flex-wrap gap-2">
                                @foreach([
                                    'can_view_processes' => 'Processos',
                                    'can_view_cobrancas' => 'Cobranças',
                                    'can_open_demands' => 'Abrir demandas',
                                    'can_view_demands' => 'Ver demandas',
                                    'can_view_documents' => 'Documentos',
                                    'can_view_financial_summary' => 'Financeiro',
                                ] as $field => $label)
                                    @if($item->{$field})
                                        <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ $label }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $item->is_active ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">{{ $item->is_active ? 'Ativo' : 'Inativo' }}</span>
                            <div class="mt-2 text-xs text-gray-500">Último acesso: {{ $item->last_login_at?->format('d/m/Y H:i') ?: 'nunca' }}</div>
                        </td>
                        <td class="px-6 py-4 align-top">
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('portal-user-edit-{{ $item->id }}').showModal()" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</button>
                                <form method="post" action="{{ route('clientes.portal-users.delete', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button onclick="return confirm('Excluir este usuário do portal?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                                </form>
                            </div>
                            @include('pages.clientes.portal._user-modal', ['modalId' => 'portal-user-edit-'.$item->id, 'action' => route('clientes.portal-users.update', $item), 'method' => 'PUT', 'portalUser' => $item])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6"><x-ancora.empty-state icon="fa-solid fa-user-shield" title="Sem usuários do portal" subtitle="Cadastre o primeiro acesso externo para clientes." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
</div>

@include('pages.clientes.portal._user-modal', ['modalId' => 'portal-user-create', 'action' => route('clientes.portal-users.store'), 'method' => 'POST', 'portalUser' => null])
@endsection
