@extends('layouts.app')

@section('content')
@php
    $blocksByCondominium = $condominiumsDropdown->mapWithKeys(fn ($condo) => [
        (string) $condo->id => $condo->blocks->map(fn ($block) => ['id' => $block->id, 'name' => $block->name])->values()->all(),
    ]);
    $selectedCondominium = (string) old('condominium_id', $item?->condominium_id);
    $selectedBlock = (string) old('block_id', $item?->block_id);
    $owner = $item?->owner;
    $tenant = $item?->tenant;
    $ownerPhones = old('owner_phones', collect($owner?->phones_json ?? [])->pluck('number')->filter()->values()->all() ?: ['']);
    $ownerEmails = old('owner_emails', collect($owner?->emails_json ?? [])->pluck('email')->filter()->values()->all() ?: ['']);
    $tenantPhones = old('tenant_phones', collect($tenant?->phones_json ?? [])->pluck('number')->filter()->values()->all() ?: ['']);
    $tenantEmails = old('tenant_emails', collect($tenant?->emails_json ?? [])->pluck('email')->filter()->values()->all() ?: ['']);
    $ownerAddress = $owner?->primary_address_json ?? [];
    $tenantAddress = $tenant?->primary_address_json ?? [];
@endphp

<x-ancora.section-header :title="$title" subtitle="Cadastro de unidade com condomínio, bloco e cards próprios para proprietário e locatário." />
@include('pages.clientes.partials.subnav')

<form method="post" action="{{ $mode === 'create' ? route('clientes.unidades.store') : route('clientes.unidades.update', $item) }}" enctype="multipart/form-data" class="space-y-6" x-data="unitClientForm({ blocksByCondo: @js($blocksByCondominium), condominiumId: '{{ $selectedCondominium }}', blockId: '{{ $selectedBlock }}', ownerPhones: @js(array_values($ownerPhones)), ownerEmails: @js(array_values($ownerEmails)), tenantPhones: @js(array_values($tenantPhones)), tenantEmails: @js(array_values($tenantEmails)) })" x-init="syncBlock()">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Vínculos</h3>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Condomínio</label>
                        <select name="condominium_id" x-model="condominiumId" @change="syncBlock()" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
                            <option value="">Selecione</option>
                            @foreach($condominiumsDropdown as $condo)
                                <option value="{{ $condo->id }}">{{ $condo->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Bloco / torre</label>
                        <select name="block_id" x-model="blockId" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!condominiumId || blocks.length === 0">
                            <option value="">Selecione</option>
                            <template x-for="block in blocks" :key="block.id">
                                <option :value="block.id" x-text="block.name"></option>
                            </template>
                        </select>
                    </div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Tipo de unidade</label><select name="unit_type_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="">Selecione</option>@foreach($unitTypes as $type)<option value="{{ $type->id }}" @selected((string)old('unit_type_id', $item?->unit_type_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Número da unidade</label><input name="unit_number" value="{{ old('unit_number', $item?->unit_number) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required></div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Proprietário</h3>
                    <span class="text-xs text-gray-500">Cadastro rápido reutilizado no vínculo da unidade.</span>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Nome</label><input name="owner_name" value="{{ old('owner_name', $owner?->display_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">CPF / CNPJ</label><input name="owner_cpf_cnpj" value="{{ old('owner_cpf_cnpj', $owner?->cpf_cnpj) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium text-gray-700 dark:text-gray-200">Telefones</label><button type="button" @click="ownerPhones.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
                        <template x-for="(phone, index) in ownerPhones" :key="`owner-phone-${index}`">
                            <div class="mb-2 flex gap-2"><input :name="`owner_phones[${index}]`" x-model="ownerPhones[index]" class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="Telefone"><button type="button" @click="ownerPhones.splice(index,1); if(!ownerPhones.length) ownerPhones.push('')" class="rounded-xl border border-gray-300 px-3 py-2 text-xs">Remover</button></div>
                        </template>
                    </div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium text-gray-700 dark:text-gray-200">E-mails</label><button type="button" @click="ownerEmails.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
                        <template x-for="(email, index) in ownerEmails" :key="`owner-email-${index}`">
                            <div class="mb-2 flex gap-2"><input :name="`owner_emails[${index}]`" x-model="ownerEmails[index]" class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="E-mail"><button type="button" @click="ownerEmails.splice(index,1); if(!ownerEmails.length) ownerEmails.push('')" class="rounded-xl border border-gray-300 px-3 py-2 text-xs">Remover</button></div>
                        </template>
                    </div>
                    <div class="md:col-span-2">
                        @include('pages.clientes.partials.address-fields', [
                            'prefix' => 'owner_address',
                            'address' => $ownerAddress,
                            'title' => 'Endereço do proprietário',
                            'showNotes' => false,
                        ])
                    </div>
                </div>
                <div class="mt-4"><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Observações do proprietário</label><textarea name="owner_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('owner_notes', $item?->owner_notes) }}</textarea></div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Locatário</h3>
                    <span class="text-xs text-gray-500">Preencha apenas se houver locação vinculada.</span>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Nome</label><input name="tenant_name" value="{{ old('tenant_name', $tenant?->display_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">CPF / CNPJ</label><input name="tenant_cpf_cnpj" value="{{ old('tenant_cpf_cnpj', $tenant?->cpf_cnpj) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium text-gray-700 dark:text-gray-200">Telefones</label><button type="button" @click="tenantPhones.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
                        <template x-for="(phone, index) in tenantPhones" :key="`tenant-phone-${index}`">
                            <div class="mb-2 flex gap-2"><input :name="`tenant_phones[${index}]`" x-model="tenantPhones[index]" class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="Telefone"><button type="button" @click="tenantPhones.splice(index,1); if(!tenantPhones.length) tenantPhones.push('')" class="rounded-xl border border-gray-300 px-3 py-2 text-xs">Remover</button></div>
                        </template>
                    </div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium text-gray-700 dark:text-gray-200">E-mails</label><button type="button" @click="tenantEmails.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
                        <template x-for="(email, index) in tenantEmails" :key="`tenant-email-${index}`">
                            <div class="mb-2 flex gap-2"><input :name="`tenant_emails[${index}]`" x-model="tenantEmails[index]" class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="E-mail"><button type="button" @click="tenantEmails.splice(index,1); if(!tenantEmails.length) tenantEmails.push('')" class="rounded-xl border border-gray-300 px-3 py-2 text-xs">Remover</button></div>
                        </template>
                    </div>
                    <div class="md:col-span-2">
                        @include('pages.clientes.partials.address-fields', [
                            'prefix' => 'tenant_address',
                            'address' => $tenantAddress,
                            'title' => 'Endereço do locatário',
                            'showNotes' => false,
                        ])
                    </div>
                </div>
                <div class="mt-4"><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Observações do locatário</label><textarea name="tenant_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('tenant_notes', $item?->tenant_notes) }}</textarea></div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos</h3>
                <div class="mt-4 space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Adicione um ou mais anexos à unidade e classifique cada arquivo separadamente.</p>
                    @include('pages.clientes.partials.attachment-uploader')
                </div>
            </div>

            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
                <div class="font-semibold text-gray-900 dark:text-white">Importação em massa</div>
                <p class="mt-2">É viável subir várias unidades em lote. O melhor ponto do fluxo é dentro do módulo de condomínios/unidades, usando uma planilha do Excel exportada em CSV com colunas como bloco, unidade, proprietário e locatário. Nesta versão eu não implementei o importador ainda, para não introduzir parser incompleto de XLSX sem biblioteca dedicada.</p>
            </div>

            @if($attachments->count())
                <div id="anexos" class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos</h3>
                    <div class="mt-4 space-y-3">
                        @foreach($attachments as $attachment)
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <div class="text-sm font-medium">{{ $attachment->original_name }}</div>
                                <div class="mt-2 flex gap-2"><a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs text-white">Baixar</a><button type="button" class="js-client-attachment-delete rounded-lg border border-error-300 px-3 py-2 text-xs text-error-600" data-delete-url="{{ route('clientes.attachments.delete', $attachment) }}" data-attachment-name="{{ $attachment->original_name }}">Excluir</button></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-8 flex flex-wrap gap-3" data-client-form-actions>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar</button>
        @if($mode === 'edit')
            <button type="submit" form="delete-unidade-form" onclick="return confirm('Excluir esta unidade?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600">Excluir</button>
        @endif
    </div>
</form>

@if($mode === 'edit')
    <form id="delete-unidade-form" method="post" action="{{ route('clientes.unidades.delete', $item) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

@push('scripts')
<script>
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
            <input type="hidden" name="_token" value="${document.querySelector("meta[name='csrf-token']")?.content || ''}">
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
    function unitClientForm(initialState) {
        return {
            blocksByCondo: initialState.blocksByCondo || {},
            condominiumId: initialState.condominiumId || '',
            blockId: initialState.blockId || '',
            ownerPhones: initialState.ownerPhones?.length ? initialState.ownerPhones : [''],
            ownerEmails: initialState.ownerEmails?.length ? initialState.ownerEmails : [''],
            tenantPhones: initialState.tenantPhones?.length ? initialState.tenantPhones : [''],
            tenantEmails: initialState.tenantEmails?.length ? initialState.tenantEmails : [''],
            get blocks() { return this.blocksByCondo[this.condominiumId] || []; },
            syncBlock() {
                if (!this.blocks.find(block => String(block.id) === String(this.blockId))) {
                    this.blockId = '';
                }
            },
        }
    }
</script>
@endpush
