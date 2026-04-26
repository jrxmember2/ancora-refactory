@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Categorias" subtitle="Organize os contratos por famílias documentais e mantenha o catálogo do módulo consistente." />

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[360px,1fr]">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Nova categoria</h3>
        <form method="post" action="{{ route('contratos.categories.store') }}" class="mt-5 space-y-4">
            @csrf
            <input name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Nome da categoria" required>
            <textarea name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Descrição"></textarea>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="is_active" value="1" checked> Ativa</label>
            <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar categoria</button>
        </form>
    </div>

    <div class="space-y-4">
        @forelse($items as $item)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $item->name }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->description ?: 'Sem descrição.' }}</div>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item->templates_count }} template(s) · {{ $item->contracts_count }} contrato(s)</div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="document.getElementById('edit-category-{{ $item->id }}').showModal()" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</button>
                        <form method="post" action="{{ route('contratos.categories.delete', $item) }}">@csrf @method('DELETE')<button onclick="return confirm('Excluir esta categoria?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button></form>
                    </div>
                </div>
            </div>

            <dialog id="edit-category-{{ $item->id }}" class="fixed inset-0 m-auto w-full max-w-lg rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
                <form method="post" action="{{ route('contratos.categories.update', $item) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Editar categoria</h3>
                        <button type="button" onclick="document.getElementById('edit-category-{{ $item->id }}').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs dark:border-gray-700">Fechar</button>
                    </div>
                    <div class="mt-5 space-y-4">
                        <input name="name" value="{{ $item->name }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" required>
                        <textarea name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ $item->description }}</textarea>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Ativa</label>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" onclick="document.getElementById('edit-category-{{ $item->id }}').close()" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
                        <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar</button>
                    </div>
                </form>
            </dialog>
        @empty
            <x-ancora.empty-state icon="fa-solid fa-folder-tree" title="Sem categorias" subtitle="Cadastre a primeira categoria para organizar os contratos." />
        @endforelse
    </div>
</div>
@endsection
