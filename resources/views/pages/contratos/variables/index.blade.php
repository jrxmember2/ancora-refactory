@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Variáveis" subtitle="Lista central das variáveis dinâmicas disponíveis nos templates e contratos." />

<div class="space-y-4">
    @foreach($items as $item)
        @php($variableToken = '{{' . $item->key . '}}')
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="post" action="{{ route('contratos.variables.update', $item) }}" class="grid grid-cols-1 gap-4 xl:grid-cols-[220px,1fr,220px,120px,120px]">
                @csrf
                @method('PUT')
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Chave</label>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-semibold text-brand-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-brand-200">{{ $variableToken }}</div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Rótulo</label>
                    <input name="label" value="{{ $item->label }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <textarea name="description" rows="3" class="mt-3 w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ $item->description }}</textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Origem</label>
                    <input name="source" value="{{ $item->source }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                </div>
                <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200"><input type="checkbox" name="is_active" value="1" @checked($item->is_active)> Ativa</label>
                <div class="flex items-end">
                    <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Salvar</button>
                </div>
            </form>
        </div>
    @endforeach
</div>
@endsection
