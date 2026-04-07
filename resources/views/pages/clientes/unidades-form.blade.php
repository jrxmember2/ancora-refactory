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
                <h3 class="text-base font-semibold">Vínculos</h3>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Condomínio</label>
                        <select name="condominium_id" x-model="condominiumId" @change="syncBlock()" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required>
                            <option value="">Selecione</option>
                            @foreach($condominiumsDropdown as $condo)
                                <option value="{{ $condo->id }}">{{ $condo->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Bloco / torre</label>
                        <select name="block_id" x-model="blockId" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" :disabled="!condominiumId || blocks.length === 0">
                            <option value="">Selecione</option>
                            <template x-for="block in blocks" :key="block.id">
                                <option :value="block.id" x-text="block.name"></option>
                            </template>
                        </select>
                    </div>
                    <div><label class="mb-1.5 block text-sm font-medium">Tipo de unidade</label><select name="unit_type_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="">Selecione</option>@foreach($unitTypes as $type)<option value="{{ $type->id }}" @selected((string)old('unit_type_id', $item?->unit_type_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium">Número da unidade</label><input name="unit_number" value="{{ old('unit_number', $item?->unit_number) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" required></div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold">Proprietário</h3>
                    <span class="text-xs text-gray-500">Cadastro rápido reutilizado no vínculo da unidade.</span>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium">Nome</label><input name="owner_name" value="{{ old('owner_name', $owner?->display_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div><label class="mb-1.5 block text-sm font-medium">CPF / CNPJ</label><input name="owner_cpf_cnpj" value="{{ old('owner_cpf_cnpj', $owner?->cpf_cnpj) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium">Telefones</label><button type="button" @click="ownerPhones.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
                        <template x-for="(phone, index) in ownerPhones" :key="`owner-phone-${index}`">
                            <div class="mb-2 flex gap-2"><input :name="`owner_phones[${index}]`" x-model="ownerPhones[index]" class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="Telefone"><button type="button" @click="ownerPhones.splice(index,1); if(!ownerPhones.length) ownerPhones.push('')" class="rounded-xl border border-gray-300 px-3 py-2 text-xs">Remover</button></div>
                        </template>
                    </div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium">E-mails</label><button type="button" @click="ownerEmails.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
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
                <div class="mt-4"><label class="mb-1.5 block text-sm font-medium">Observações do proprietário</label><textarea name="owner_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('owner_notes', $item?->owner_notes) }}</textarea></div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold">Locatário</h3>
                    <span class="text-xs text-gray-500">Preencha apenas se houver locação vinculada.</span>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium">Nome</label><input name="tenant_name" value="{{ old('tenant_name', $tenant?->display_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div><label class="mb-1.5 block text-sm font-medium">CPF / CNPJ</label><input name="tenant_cpf_cnpj" value="{{ old('tenant_cpf_cnpj', $tenant?->cpf_cnpj) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"></div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium">Telefones</label><button type="button" @click="tenantPhones.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
                        <template x-for="(phone, index) in tenantPhones" :key="`tenant-phone-${index}`">
                            <div class="mb-2 flex gap-2"><input :name="`tenant_phones[${index}]`" x-model="tenantPhones[index]" class="h-11 flex-1 rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700" placeholder="Telefone"><button type="button" @click="tenantPhones.splice(index,1); if(!tenantPhones.length) tenantPhones.push('')" class="rounded-xl border border-gray-300 px-3 py-2 text-xs">Remover</button></div>
                        </template>
                    </div>
                    <div class="md:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between"><label class="text-sm font-medium">E-mails</label><button type="button" @click="tenantEmails.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button></div>
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
                <div class="mt-4"><label class="mb-1.5 block text-sm font-medium">Observações do locatário</label><textarea name="tenant_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 dark:border-gray-700">{{ old('tenant_notes', $item?->tenant_notes) }}</textarea></div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold">Anexos</h3>
                <div class="mt-4 space-y-4">
                    <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                        <i class="fa-solid fa-paperclip"></i>
                        <span>Escolher arquivos para anexar</span>
                        <input type="file" name="attachments[]" multiple class="sr-only">
                    </label>
                    <select name="attachment_role" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700"><option value="documento">Documento</option><option value="contrato">Contrato</option><option value="outro">Outro</option></select>
                </div>
            </div>

            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
                <div class="font-semibold text-gray-900 dark:text-white">Importação em massa</div>
                <p class="mt-2">É viável subir várias unidades em lote. O melhor ponto do fluxo é dentro do módulo de condomínios/unidades, usando uma planilha do Excel exportada em CSV com colunas como bloco, unidade, proprietário e locatário. Nesta versão eu não implementei o importador ainda, para não introduzir parser incompleto de XLSX sem biblioteca dedicada.</p>
            </div>

            @if($attachments->count())
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold">Anexos</h3>
                    <div class="mt-4 space-y-3">
                        @foreach($attachments as $attachment)
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <div class="text-sm font-medium">{{ $attachment->original_name }}</div>
                                <div class="mt-2 flex gap-2"><a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs text-white">Baixar</a><form method="post" action="{{ route('clientes.attachments.delete', $attachment) }}">@csrf @method('DELETE')<button class="rounded-lg border border-error-300 px-3 py-2 text-xs text-error-600">Excluir</button></form></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="flex gap-3"><button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}</button></div>
</form>

@if($mode === 'edit')
    <form method="post" action="{{ route('clientes.unidades.delete', $item) }}" class="mt-3">
        @csrf
        @method('DELETE')
        <button onclick="return confirm('Excluir esta unidade?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600">Excluir</button>
    </form>
@endif
@endsection

@push('scripts')
<script>
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
