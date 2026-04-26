@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Centros de Custo" subtitle="Organize o financeiro por nucleo operacional, area interna e segmento de resultado.">
    <x-financeiro.export-actions scope="cost-centers" :allow-selection="false" />
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,2fr]">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Novo centro de custo</h3>
        <form method="post" action="{{ route('financeiro.cost-centers.store') }}" class="mt-4 space-y-4">
            @csrf
            <input type="text" name="name" placeholder="Nome" required class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <input type="text" name="description" placeholder="Descricao" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
            <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200"><input type="checkbox" name="is_active" value="1" checked> Centro ativo</label>
            <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar centro</button>
        </form>
    </div>

    <div class="space-y-4">
        @forelse($items as $item)
            <details class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $item->name }}</div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->description ?: 'Sem descricao' }}</div>
                    </div>
                    <span class="text-sm {{ $item->is_active ? 'text-emerald-600 dark:text-emerald-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $item->is_active ? 'Ativo' : 'Inativo' }}</span>
                </summary>
                <form method="post" action="{{ route('financeiro.cost-centers.update', $item) }}" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @csrf
                    <input type="text" name="name" value="{{ $item->name }}" required class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <input type="text" name="description" value="{{ $item->description }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-200 md:col-span-2"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Centro ativo</label>
                    <div class="flex justify-end gap-3 md:col-span-2">
                        <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar</button>
                    </div>
                </form>
                <form method="post" action="{{ route('financeiro.cost-centers.delete', $item) }}" class="mt-3 text-right">
                    @csrf
                    <button class="rounded-xl border border-rose-200 px-4 py-3 text-sm font-medium text-rose-600 dark:border-rose-900 dark:text-rose-300">Excluir</button>
                </form>
            </details>
        @empty
            <x-ancora.empty-state icon="fa-solid fa-sitemap" title="Sem centros de custo" subtitle="Cadastre centros para melhorar DRE, despesas e visao gerencial." />
        @endforelse
    </div>
</div>
@endsection
