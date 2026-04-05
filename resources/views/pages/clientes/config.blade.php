@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Configurações de clientes" subtitle="Tipos reutilizáveis para perfil/papel, condomínio e unidade." />
@include('pages.clientes.partials.subnav')

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,1.5fr]">
    <form method="post" action="{{ route('clientes.config.types.store') }}" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Novo tipo</h3>
        <div class="mt-4 space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Escopo</label>
                <select name="scope" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    @foreach($scopeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Nome</label>
                <input name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Ordem</label>
                <input type="number" name="sort_order" value="999" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            </div>
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar tipo</button>
        </div>
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tipos cadastrados</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500">
                        <th class="px-4 py-3">Escopo</th>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Ordem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
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
@endsection
