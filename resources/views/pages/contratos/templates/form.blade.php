@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

@section('content')
<x-ancora.section-header :title="$mode === 'create' ? 'Novo template' : ($item->name ?: 'Editar template')" subtitle="Modelos reutilizáveis com variáveis dinâmicas, cabeçalho, rodapé e margens personalizadas.">
    <a href="{{ route('contratos.templates.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
</x-ancora.section-header>

<form method="post" action="{{ $mode === 'create' ? route('contratos.templates.store') : route('contratos.templates.update', $item) }}" class="space-y-6">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,360px]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados do template</h3>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label><input name="name" value="{{ old('name', $item?->name) }}" required class="{{ $inputClass }}"></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de documento</label><select name="document_type" required class="{{ $inputClass }}">@foreach($typeOptions as $type)<option value="{{ $type }}" @selected(old('document_type', $item?->document_type) === $type)>{{ $type }}</option>@endforeach</select></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Categoria</label><select name="category_id" class="{{ $inputClass }}"><option value="">Selecione</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) old('category_id', $item?->category_id) === (int) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Orientação</label><select name="page_orientation" class="{{ $inputClass }}">@foreach($orientationOptions as $key => $label)<option value="{{ $key }}" @selected(old('page_orientation', $item?->page_orientation ?? 'portrait') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="md:col-span-2"><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Descrição</label><input name="description" value="{{ old('description', $item?->description) }}" class="{{ $inputClass }}"></div>
                    <div class="grid grid-cols-2 gap-4 md:col-span-2 xl:grid-cols-4">
                        @php($margins = old('margins_json', $item?->margins_json ?? ['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3]))
                        <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Margem sup. (cm)</label><input name="margin_top" value="{{ $margins['top'] ?? 3 }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Margem dir. (cm)</label><input name="margin_right" value="{{ $margins['right'] ?? 2 }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Margem inf. (cm)</label><input name="margin_bottom" value="{{ $margins['bottom'] ?? 2 }}" class="{{ $inputClass }}"></div>
                        <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Margem esq. (cm)</label><input name="margin_left" value="{{ $margins['left'] ?? 3 }}" class="{{ $inputClass }}"></div>
                    </div>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item?->is_active ?? true))> Template ativo</label>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Conteúdo</h3>
                <div class="mt-5">
                    @include('pages.contratos.partials.rich-editor', [
                        'editorId' => 'contract-template-content',
                        'name' => 'content_html',
                        'value' => old('content_html', $item?->content_html),
                        'placeholder' => 'Escreva o conteúdo base do template.',
                        'minHeight' => '420px',
                        'variableDefinitions' => $variableDefinitions,
                        'showVariablePicker' => true,
                    ])
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cabeçalho personalizado</label>
                        <textarea name="header_html" rows="5" class="{{ $textareaClass }}">{{ old('header_html', $item?->header_html) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Rodapé personalizado</label>
                        <textarea name="footer_html" rows="5" class="{{ $textareaClass }}">{{ old('footer_html', $item?->footer_html) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Variáveis liberadas</h3>
                <div class="mt-4 space-y-3">
                    @php($selectedVariables = old('available_variables', $item?->available_variables_json ?? []))
                    @foreach($variableDefinitions as $variable)
                        @php($variableToken = '{{' . ($variable['key'] ?? '') . '}}')
                        <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="available_variables[]" value="{{ $variable['key'] }}" @checked(in_array($variable['key'], $selectedVariables, true))>
                            <span>
                                <span class="block font-semibold">{{ $variableToken }}</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $variable['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ route('contratos.templates.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar template</button>
    </div>
</form>
@endsection
