@extends('layouts.app')

@section('content')
@php
    $item = $condominio ?? null;
    $address = $item?->address_json ?? [];
    $selectedInactive = old('is_inactive', ($item && !$item->is_active) ? 1 : 0);
    $blocksText = old('blocks_text', isset($blocksText) ? $blocksText : '');
    $attachments = $attachments ?? collect();

    $groupedAttachments = [
        'convention' => $attachments->filter(fn ($attachment) => str_starts_with($attachment->original_name, 'Convenção condominial -')),
        'regiment' => $attachments->filter(fn ($attachment) => str_starts_with($attachment->original_name, 'Regimento interno -')),
        'atas' => $attachments->filter(fn ($attachment) => str_starts_with($attachment->original_name, 'ATA -')),
        'others' => $attachments->reject(fn ($attachment) =>
            str_starts_with($attachment->original_name, 'Convenção condominial -')
            || str_starts_with($attachment->original_name, 'Regimento interno -')
            || str_starts_with($attachment->original_name, 'ATA -')
        ),
    ];
@endphp

<x-ancora.section-header
    :title="$mode === 'create' ? 'Novo condomínio' : 'Editar condomínio'"
    subtitle="Cadastro da área condominial com tipo, endereço, síndico, documentos e anexos."
/>

<form
    method="post"
    action="{{ $mode === 'create' ? route('clientes.condominios.store') : route('clientes.condominios.update', $item) }}"
    enctype="multipart/form-data"
    class="space-y-6"
    x-data="condominiumForm({ inactive: {{ $selectedInactive ? 'true' : 'false' }} })"
    x-init="init()"
>
    @csrf
    @if($mode === 'edit')
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Nome do condomínio</label>
                        <input
                            name="name"
                            value="{{ old('name', $item?->name) }}"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"
                            required
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Tipo</label>
                        <select
                            name="condominium_type_id"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"
                        >
                            <option value="">Selecione</option>
                            @foreach($condominiumTypes as $type)
                                <option
                                    value="{{ $type->id }}"
                                    @selected((string) old('condominium_type_id', $item?->condominium_type_id) === (string) $type->id)
                                >
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">CNPJ</label>
                        <input
                            name="cnpj"
                            value="{{ old('cnpj', $item?->cnpj) }}"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"
                            placeholder="00.000.000/0000-00"
                            inputmode="numeric"
                            maxlength="18"
                            x-ref="cnpj"
                            @input="maskCnpj()"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Síndico vinculado</label>
                        <select
                            name="syndico_entity_id"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"
                            required
                        >
                            <option value="">Selecione</option>
                            @foreach($syndics as $syndic)
                                <option
                                    value="{{ $syndic->id }}"
                                    @selected((string) old('syndico_entity_id', $item?->syndico_entity_id) === (string) $syndic->id)
                                >
                                    {{ $syndic->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Administradora</label>
                        <select
                            name="administradora_entity_id"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"
                        >
                            <option value="">Selecione</option>
                            @foreach($administradorasList as $admin)
                                <option
                                    value="{{ $admin->id }}"
                                    @selected((string) old('administradora_entity_id', $item?->administradora_entity_id) === (string) $admin->id)
                                >
                                    {{ $admin->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="has_blocks" value="1" @checked(old('has_blocks', $item?->has_blocks))>
                        Possui blocos / torres
                    </label>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Blocos / torres</label>
                        <textarea
                            name="blocks_text"
                            rows="5"
                            class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700"
                            placeholder="Um bloco por linha"
                        >{{ $blocksText }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Caso o condomínio tenha múltiplos blocos, informe um por linha.</p>
                    </div>
                </div>
            </div>

            @include('pages.clientes.partials.address-fields', [
                'prefix' => 'address',
                'address' => $address,
                'title' => 'Endereço',
            ])

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Documentos</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Suba a convenção, o regimento interno e quantas ATAs forem necessárias.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div data-file-preview>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Convenção condominial</label>

                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            <span>Selecionar arquivo</span>
                            <input type="file" name="document_convention" class="sr-only" data-file-input>
                        </label>

                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>
                            {{ $groupedAttachments['convention']->pluck('original_name')->implode(', ') ?: 'Nenhum arquivo selecionado' }}
                        </div>

                        @if($groupedAttachments['convention']->count())
                            <div class="mt-3 space-y-2">
                                @foreach($groupedAttachments['convention'] as $attachment)
                                    <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                        <div class="font-medium">{{ $attachment->original_name }}</div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                            <button type="button" class="js-client-attachment-delete rounded-md border border-error-300 px-2 py-1 text-error-600" data-delete-url="{{ route('clientes.attachments.delete', $attachment) }}" data-attachment-name="{{ $attachment->original_name }}">Excluir</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div data-file-preview>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Regimento interno</label>

                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-file-arrow-up"></i>
                            <span>Selecionar arquivo</span>
                            <input type="file" name="document_regiment" class="sr-only" data-file-input>
                        </label>

                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>
                            {{ $groupedAttachments['regiment']->pluck('original_name')->implode(', ') ?: 'Nenhum arquivo selecionado' }}
                        </div>

                        @if($groupedAttachments['regiment']->count())
                            <div class="mt-3 space-y-2">
                                @foreach($groupedAttachments['regiment'] as $attachment)
                                    <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                        <div class="font-medium">{{ $attachment->original_name }}</div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                            <button type="button" class="js-client-attachment-delete rounded-md border border-error-300 px-2 py-1 text-error-600" data-delete-url="{{ route('clientes.attachments.delete', $attachment) }}" data-attachment-name="{{ $attachment->original_name }}">Excluir</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div data-file-preview>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">ATAs</label>

                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-files"></i>
                            <span>Selecionar um ou mais arquivos</span>
                            <input type="file" name="document_atas[]" multiple class="sr-only" data-file-input data-multiple>
                        </label>

                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>
                            {{ $groupedAttachments['atas']->pluck('original_name')->implode(', ') ?: 'Nenhum arquivo selecionado' }}
                        </div>

                        @if($groupedAttachments['atas']->count())
                            <div class="mt-3 space-y-2">
                                @foreach($groupedAttachments['atas'] as $attachment)
                                    <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                        <div class="font-medium">{{ $attachment->original_name }}</div>
                                        <div class="mt-2 flex gap-2">
                                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                            <button type="button" class="js-client-attachment-delete rounded-md border border-error-300 px-2 py-1 text-error-600" data-delete-url="{{ route('clientes.attachments.delete', $attachment) }}" data-attachment-name="{{ $attachment->original_name }}">Excluir</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Status</h3>

                <div class="mt-4 space-y-4">
                    <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_inactive" value="1" x-model="inactive">
                        Inativo
                    </label>

                    <div x-bind:class="inactive ? '' : 'opacity-60'">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Motivo da inativação</label>
                        <input
                            name="inactive_reason"
                            value="{{ old('inactive_reason', $item?->inactive_reason) }}"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"
                            :disabled="!inactive"
                        >
                    </div>

                    <div x-bind:class="inactive ? '' : 'opacity-60'">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Fim do contrato</label>
                        <input
                            type="date"
                            name="contract_end_date"
                            value="{{ old('contract_end_date', $item?->contract_end_date?->format('Y-m-d') ?? $item?->contract_end_date) }}"
                            class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"
                            :disabled="!inactive"
                        >
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos adicionais</h3>

                <div id="anexos" class="mt-4 space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Adicione um ou mais anexos extras e categorize cada arquivo para manter o cadastro organizado.</p>

                    @include('pages.clientes.partials.attachment-uploader')

                    @if($groupedAttachments['others']->count())
                        <div class="space-y-2">
                            @foreach($groupedAttachments['others'] as $attachment)
                                <div class="rounded-lg border border-gray-200 px-3 py-2 text-xs dark:border-gray-800">
                                    <div class="font-medium">{{ $attachment->original_name }}</div>
                                    <div class="mt-2 flex gap-2">
                                        <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-md bg-brand-500 px-2 py-1 text-white">Baixar</a>
                                        <button type="button" class="js-client-attachment-delete rounded-md border border-error-300 px-2 py-1 text-error-600" data-delete-url="{{ route('clientes.attachments.delete', $attachment) }}" data-attachment-name="{{ $attachment->original_name }}">Excluir</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if($attachments->count())
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo de documentos cadastrados</h3>
                    <div class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Total anexado: {{ $attachments->count() }} arquivo(s)
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-8 flex flex-wrap gap-3" data-client-form-actions>
        <button type="submit" class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">
            Salvar
        </button>
        @if($mode === 'edit')
            <button type="submit" form="delete-condominio-form" onclick="return confirm('Excluir este condomínio?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600">
                Excluir
            </button>
        @endif
    </div>
</form>

@if($mode === 'edit')
    <form id="delete-condominio-form" method="post" action="{{ route('clientes.condominios.delete', $item) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

@push('scripts')
<script>
function condominiumForm(initialState) {
    return {
        inactive: !!initialState.inactive,
        init() {
            this.maskCnpj();
        },
        maskCnpj() {
            if (!this.$refs.cnpj) return;

            let digits = this.$refs.cnpj.value.replace(/\D/g, '').slice(0, 14);
            digits = digits
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');

            this.$refs.cnpj.value = digits;
        },
    }
}

function ancoraAttachmentRepeater(initialRoles = ['documento']) {
    const roles = Array.isArray(initialRoles) && initialRoles.length ? initialRoles : ['documento'];
    return {
        rows: roles.map((role, index) => ({ id: index + 1, role: role || 'documento', fileName: '' })),
        nextId: roles.length + 1,
        addRow(role = 'documento') {
            this.rows.push({ id: this.nextId++, role, fileName: '' });
        },
        removeRow(index) {
            if (this.rows.length === 1) {
                this.rows[0].fileName = '';
                return;
            }
            this.rows.splice(index, 1);
        },
        updateFile(index, event) {
            const files = Array.from(event.target.files || []);
            this.rows[index].fileName = files.map((file) => file.name).join(', ');
        },
    }
}

function deleteClientAttachment(button) {
    const url = button.dataset.deleteUrl;
    const name = button.dataset.attachmentName || 'este anexo';
    if (!url || !confirm(`Excluir ${name}?`)) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.innerHTML = `
        <input type="hidden" name="_token" value="${document.querySelector('meta[name=\'csrf-token\']')?.content || ''}">
        <input type="hidden" name="_method" value="DELETE">
    `;
    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('.js-client-attachment-delete');
    if (!button) return;
    deleteClientAttachment(button);
});

document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-file-input]')) return;

    const wrapper = event.target.closest('[data-file-preview]');
    const label = wrapper?.querySelector('[data-file-name]');
    if (!label) return;

    const files = Array.from(event.target.files || []);
    if (!files.length) return;

    label.textContent = files.map((file) => file.name).join(', ');
});
</script>
@endpush
