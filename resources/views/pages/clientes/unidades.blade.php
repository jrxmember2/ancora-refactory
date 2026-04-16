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
            <p>3. Envie o arquivo para abrir a prévia. A criação só acontece depois da conferência e do clique em executar.</p>
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
            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Pré-visualizar</button>
        </div>
        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">Pode ser CSV separado por vírgula ou ponto e vírgula. Duplicidades por condomínio + bloco + unidade serão bloqueadas.</div>
    </form>
</div>

@if(!empty($importPreview))
    @php($previewSummary = $importPreview['summary'] ?? ['total' => 0, 'ready' => 0, 'errors' => 0, 'new_blocks' => 0])
    <dialog id="unit-import-preview-modal" class="fixed inset-0 m-auto max-h-[90vh] w-full max-w-6xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900" data-auto-open>
        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Prévia da importação de unidades</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $importPreview['condominium_name'] ?? 'Condomínio selecionado' }}</p>
                </div>
                <button type="button" onclick="document.getElementById('unit-import-preview-modal').close()" class="rounded-full border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 px-4 py-3 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500">Linhas</div><div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $previewSummary['total'] ?? 0 }}</div></div>
                <div class="rounded-2xl border border-success-200 bg-success-50 px-4 py-3 dark:border-success-800 dark:bg-success-500/10"><div class="text-xs uppercase tracking-[0.16em] text-success-700 dark:text-success-300">Prontas</div><div class="mt-1 text-xl font-semibold text-success-700 dark:text-success-300">{{ $previewSummary['ready'] ?? 0 }}</div></div>
                <div class="rounded-2xl border border-error-200 bg-error-50 px-4 py-3 dark:border-error-800 dark:bg-error-500/10"><div class="text-xs uppercase tracking-[0.16em] text-error-700 dark:text-error-300">Pendências</div><div class="mt-1 text-xl font-semibold text-error-700 dark:text-error-300">{{ $previewSummary['errors'] ?? 0 }}</div></div>
                <div class="rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 dark:border-brand-800 dark:bg-brand-500/10"><div class="text-xs uppercase tracking-[0.16em] text-brand-700 dark:text-brand-300">Novos blocos</div><div class="mt-1 text-xl font-semibold text-brand-700 dark:text-brand-300">{{ $previewSummary['new_blocks'] ?? 0 }}</div></div>
            </div>
        </div>

        <div class="overflow-x-auto px-6 py-5">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-[0.16em] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="py-3 pr-4">Linha</th>
                        <th class="py-3 pr-4">Condomínio</th>
                        <th class="py-3 pr-4">Bloco</th>
                        <th class="py-3 pr-4">Unidade</th>
                        <th class="py-3 pr-4">Proprietário</th>
                        <th class="py-3 pr-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach(($importPreview['rows'] ?? []) as $row)
                        <tr>
                            <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">{{ $row['row_number'] ?? '—' }}</td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ $row['condominium_name'] ?? '—' }}</td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">
                                {{ ($row['block_name'] ?? '') !== '' ? $row['block_name'] : 'Sem bloco' }}
                                @if(($row['block_status'] ?? '') === 'new')
                                    <span class="ml-2 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">novo</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">{{ $row['unit_number'] ?? '—' }}</td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">
                                <div>{{ ($row['owner_name'] ?? '') !== '' ? $row['owner_name'] : '—' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['owner_document'] ?? '' }}</div>
                            </td>
                            <td class="py-3 pr-4">
                                @if(($row['status'] ?? '') === 'ready')
                                    <span class="rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-300">Pronta para criar</span>
                                @else
                                    <div class="rounded-xl border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300">{{ implode(' ', $row['messages'] ?? ['Pendência na linha.']) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap justify-end gap-3 border-t border-gray-100 px-6 py-5 dark:border-gray-800">
            <button type="button" onclick="document.getElementById('unit-import-preview-modal').close()" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Voltar e revisar</button>
            <form method="post" action="{{ route('clientes.unidades.import.execute') }}">
                @csrf
                <input type="hidden" name="import_token" value="{{ $importPreviewToken }}">
                <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50" @disabled(($previewSummary['errors'] ?? 0) > 0 || ($previewSummary['ready'] ?? 0) === 0)>Executar importação</button>
            </form>
        </div>
    </dialog>
@endif

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

<form id="bulk-delete-units-form" method="post" action="{{ route('clientes.unidades.bulk-delete') }}">
    @csrf
    @method('DELETE')
</form>

<div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="text-sm text-gray-600 dark:text-gray-300">Selecione unidades na lista para excluir em massa. Unidades com OS vinculada continuam protegidas.</div>
    <button type="button" data-open-bulk-delete class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-600 hover:bg-error-50 dark:text-error-300 dark:hover:bg-error-500/10">Excluir selecionadas</button>
</div>

<dialog id="bulk-delete-units-modal" class="fixed inset-0 m-auto w-full max-w-lg rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirmar exclusão em massa</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Você está prestes a excluir <strong data-bulk-delete-count>0</strong> unidade(s). Essa ação não remove proprietários/locatários, mas remove o vínculo da unidade.</p>
    </div>
    <div class="flex justify-end gap-3 px-6 py-5">
        <button type="button" onclick="document.getElementById('bulk-delete-units-modal').close()" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
        <button type="submit" form="bulk-delete-units-form" class="rounded-xl bg-error-500 px-4 py-3 text-sm font-medium text-white hover:bg-error-600">Excluir unidades</button>
    </div>
</dialog>

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
                        <th class="px-6 py-4"><input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500" data-unit-bulk-toggle aria-label="Selecionar todas as unidades da página"></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="condominium" label="Condomínio" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="block" label="Bloco" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="unit" label="Unidade" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="owner" label="Proprietário" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4"><x-ancora.sort-link field="tenant" label="Locatário" :sort="$sortState['sort'] ?? null" :direction="$sortState['direction'] ?? null" /></th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($items as $item)
                        <tr>
                            <td class="px-6 py-4"><input type="checkbox" name="unit_ids[]" value="{{ $item->id }}" form="bulk-delete-units-form" class="h-4 w-4 rounded border-gray-300 text-brand-500" data-unit-bulk-checkbox aria-label="Selecionar unidade {{ $item->unit_number }}"></td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->condominium?->name }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-200">{{ $item->block?->name ?: '—' }}</td>
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

    document.querySelectorAll('dialog[data-auto-open]').forEach((dialog) => {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    });

    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-unit-bulk-toggle]')) return;
        document.querySelectorAll('[data-unit-bulk-checkbox]').forEach((checkbox) => {
            checkbox.checked = event.target.checked;
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-open-bulk-delete]');
        if (!button) return;
        const selected = document.querySelectorAll('[data-unit-bulk-checkbox]:checked');
        if (!selected.length) {
            alert('Selecione ao menos uma unidade para excluir.');
            return;
        }
        const modal = document.getElementById('bulk-delete-units-modal');
        modal?.querySelector('[data-bulk-delete-count]')?.replaceChildren(document.createTextNode(String(selected.length)));
        modal?.showModal();
    });
</script>
@endpush
