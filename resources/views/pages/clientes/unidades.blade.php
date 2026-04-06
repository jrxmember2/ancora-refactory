@extends('layouts.app')

@section('content')
@php
    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
    $selectClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100';
@endphp

<x-ancora.section-header title="Unidades" subtitle="Vínculo entre condomínio, proprietário e locatário.">
    <a href="{{ route('clientes.unidades.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova unidade</a>
</x-ancora.section-header>
@include('pages.clientes.partials.subnav')

<div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr,1.35fr]">
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-5 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
        <strong class="text-gray-900 dark:text-white">Importação em massa por CSV</strong>
        <div class="mt-3 space-y-2">
            <p>1. Escolha o condomínio da importação.</p>
            <p>2. Baixe o arquivo modelo, preencha uma linha por unidade e salve em CSV.</p>
            <p>3. Envie o arquivo. O sistema cria unidades novas e atualiza unidades já existentes quando encontrar a mesma combinação de condomínio + bloco + número da unidade.</p>
        </div>
        <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-300">
            <div class="font-semibold text-gray-900 dark:text-white">Colunas aceitas no modelo</div>
            <div class="mt-2 break-words"><code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">bloco</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">unit_number</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">unit_type</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">owner_name</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">owner_document</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">owner_phone</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">owner_email</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">tenant_name</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">tenant_document</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">tenant_phone</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">tenant_email</code>.</div>
        </div>
        <div class="mt-4">
            <a href="{{ asset('examples/clientes-unidades-importacao-exemplo.csv') }}" class="inline-flex items-center gap-2 rounded-xl border border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" download>
                <i class="fa-solid fa-file-arrow-down"></i>
                <span>Baixar CSV de exemplo</span>
            </a>
        </div>
    </div>

    <form method="post" action="{{ route('clientes.unidades.import') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" data-file-preview>
        @csrf
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[1.2fr,1fr,auto] md:items-end">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Condomínio da importação</label>
                <select name="import_condominium_id" class="{{ $selectClass }}" required>
                    <option value="">Selecione</option>
                    @foreach($condominiumsDropdown ?? [] as $condo)
                        <option value="{{ $condo->id }}">{{ $condo->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Arquivo CSV</label>
                <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                    <i class="fa-solid fa-file-csv"></i>
                    <span>Escolher CSV</span>
                    <input type="file" name="import_file" accept=".csv,text/csv" class="sr-only" data-file-input>
                </label>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>Nenhum arquivo selecionado</div>
            </div>
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Importar</button>
        </div>
        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">Pode ser CSV separado por vírgula ou ponto e vírgula. O arquivo exemplo já está no formato esperado.</div>
    </form>
</div>

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="get" class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar unidade..." class="{{ $fieldClass }}">
        <select name="condominium_id" class="{{ $selectClass }}">
            <option value="">Todos os condomínios</option>
            @foreach($condominiumsDropdown ?? [] as $condo)
                <option value="{{ $condo->id }}" @selected((string) ($filters['condominium_id'] ?? '') === (string) $condo->id)>{{ $condo->name }}</option>
            @endforeach
        </select>
        <div class="flex gap-3">
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Filtrar</button>
            <a href="{{ route('clientes.unidades') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Limpar</a>
        </div>
    </form>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    @if($items->count() === 0)
        <div class="p-6">
            <x-ancora.empty-state icon="fa-solid fa-door-closed" title="Sem unidades" subtitle="Nenhuma unidade foi cadastrada até o momento." />
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                    <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Condomínio</th>
                        <th class="px-6 py-4">Unidade</th>
                        <th class="px-6 py-4">Proprietário</th>
                        <th class="px-6 py-4">Locatário</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->condominium?->name }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->unit_number }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->owner?->display_name ?: '—' }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->tenant?->display_name ?: '—' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <button type="button" onclick="document.getElementById('view-unit-{{ $item->id }}').showModal()" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Visualizar</button>
                                    <a href="{{ route('clientes.unidades.edit', $item) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Editar</a>
                                    <form method="post" action="{{ route('clientes.unidades.delete', $item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button onclick="return confirm('Excluir esta unidade?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                                    </form>
                                </div>

                                <dialog id="view-unit-{{ $item->id }}" class="fixed inset-0 m-auto max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
                                    <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Unidade {{ $item->unit_number }}</h3>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->condominium?->name }}</p>
                                            </div>
                                            <button type="button" onclick="document.getElementById('view-unit-{{ $item->id }}').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 px-6 py-5 text-sm md:grid-cols-2">
                                        <div>
                                            <span class="block text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Bloco / torre</span>
                                            <div class="mt-1 text-gray-900 dark:text-white">{{ $item->block?->name ?: '—' }}</div>
                                        </div>
                                        <div>
                                            <span class="block text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Tipo</span>
                                            <div class="mt-1 text-gray-900 dark:text-white">{{ $item->type?->name ?: '—' }}</div>
                                        </div>
                                        <div>
                                            <span class="block text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Proprietário</span>
                                            <div class="mt-1 text-gray-900 dark:text-white">{{ $item->owner?->display_name ?: '—' }}</div>
                                        </div>
                                        <div>
                                            <span class="block text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Locatário</span>
                                            <div class="mt-1 text-gray-900 dark:text-white">{{ $item->tenant?->display_name ?: '—' }}</div>
                                        </div>
                                    </div>
                                </dialog>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">{{ $items->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-file-input]')) return;
        const wrapper = event.target.closest('[data-file-preview]');
        const label = wrapper?.querySelector('[data-file-name]');
        if (!label) return;
        const files = Array.from(event.target.files || []);
        label.textContent = files.length ? files.map((file) => file.name).join(', ') : 'Nenhum arquivo selecionado';
    });
</script>
@endpush
