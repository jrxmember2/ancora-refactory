@extends('layouts.app')

@php
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200';
@endphp

@section('content')
<x-ancora.section-header title="Configuracoes de clientes" subtitle="Tipos reutilizaveis para perfil/papel, condominio e unidade.">
    <button type="button" onclick="document.getElementById('portal-app-login-logs-modal').showModal()" class="{{ $buttonClass }}">Logins do app</button>
</x-ancora.section-header>
@include('pages.clientes.partials.subnav')

<div id="portal-app-login-logs" class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Portal do Cliente / App mobile</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Audite os logins feitos no aplicativo Ancora Clientes, com IP, dispositivo, versao e localizacao quando a infraestrutura informar esses dados.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="document.getElementById('portal-app-login-logs-modal').showModal()" class="{{ $buttonClass }}">Abrir logins</button>
            @if($portalAppLoginLogsReady && $portalAppLoginLogCount > 0)
                <a href="{{ route('clientes.config.portal-app-logins.export') }}" class="{{ $softButtonClass }}">Exportar XLSX</a>
            @else
                <span class="{{ $softButtonClass }} opacity-60">Exportar XLSX</span>
            @endif
        </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-100 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/40">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Registros</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format((int) $portalAppLoginLogCount, 0, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/40">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Ultimo login app</div>
            <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $portalAppLoginLogs->first()?->created_at?->format('d/m/Y H:i') ?: 'Nenhum login registrado' }}</div>
        </div>
        <div class="rounded-2xl border border-gray-100 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-gray-900/40">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Localizacao</div>
            <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $portalAppLoginLogs->first()?->location_label ?: 'Somente IP por enquanto' }}</div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,1.5fr]">
    <form method="post" action="{{ route('clientes.config.types.store') }}" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Novo tipo</h3>
        <div class="mt-4 space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Escopo</label>
                <select name="scope" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:text-gray-100 dark:placeholder:text-gray-500">
                    @foreach($scopeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:text-gray-100 dark:placeholder:text-gray-500" required>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Ordem</label>
                <input type="number" name="sort_order" value="999" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-gray-900 placeholder:text-gray-400 dark:border-gray-700 dark:text-gray-100 dark:placeholder:text-gray-500">
            </div>
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar tipo</button>
        </div>
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Tipos cadastrados</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500">
                        <th class="px-4 py-3">Escopo</th>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Ordem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-200">
                    @foreach($types as $type)
                        <tr>
                            <td class="px-4 py-3">{{ $scopeOptions[$type->scope] ?? $type->scope }}</td>
                            <td class="px-4 py-3">{{ $type->name }}</td>
                            <td class="px-4 py-3">{{ $type->sort_order }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('pages.clientes.portal._app-login-logs-modal')
@endsection
