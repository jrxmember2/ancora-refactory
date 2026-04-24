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

    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
    $selectClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-gray-800 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-gray-800 placeholder:text-gray-400 shadow-theme-xs dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-100 dark:placeholder:text-gray-500';
    $secondaryButtonClass = 'rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200 dark:hover:bg-gray-800';
@endphp

<x-ancora.section-header :title="$title" subtitle="Cadastro de unidade com condomínio, bloco e cards próprios para proprietário e locatário." />
@include('pages.clientes.partials.subnav')

<form
    id="unidade-form"
    method="post"
    action="{{ $mode === 'create' ? route('clientes.unidades.store') : route('clientes.unidades.update', $item) }}"
    enctype="multipart/form-data"
    class="space-y-6"
    data-clientes-form
    x-data="unitClientForm({ blocksByCondo: @js($blocksByCondominium), condominiumId: '{{ $selectedCondominium }}', blockId: '{{ $selectedBlock }}', ownerPhones: @js(array_values($ownerPhones)), ownerEmails: @js(array_values($ownerEmails)), tenantPhones: @js(array_values($tenantPhones)), tenantEmails: @js(array_values($tenantEmails)) })"
    x-init="init()"
>
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Vínculos</h3>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Condomínio</label>
                        <select name="condominium_id" x-model="condominiumId" @change="syncBlock()" class="{{ $selectClass }}" required>
                            <option value="">Selecione</option>
                            @foreach($condominiumsDropdown as $condo)
                                <option value="{{ $condo->id }}">{{ $condo->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Bloco / torre</label>
                        <select name="block_id" x-model="blockId" class="{{ $selectClass }}" :disabled="!condominiumId || blocks.length === 0">
                            <option value="">Selecione</option>
                            <template x-for="block in blocks" :key="block.id">
                                <option :value="block.id" :selected="String(block.id) === String(blockId)" x-text="block.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de unidade</label>
                        <select name="unit_type_id" class="{{ $selectClass }}">
                            <option value="">Selecione</option>
                            @foreach($unitTypes as $type)
                                <option value="{{ $type->id }}" @selected((string) old('unit_type_id', $item?->unit_type_id) === (string) $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Número da unidade</label>
                        <input name="unit_number" value="{{ old('unit_number', $item?->unit_number) }}" class="{{ $fieldClass }}" placeholder="Ex.: 101, A-203, COB-02" required data-normalize="uppercase">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Proprietário</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Cadastro rápido reutilizado no vínculo da unidade.</span>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                        <input name="owner_name" value="{{ old('owner_name', $owner?->display_name) }}" class="{{ $fieldClass }}" placeholder="Nome do proprietário" data-normalize="title">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                        <input name="owner_cpf_cnpj" value="{{ old('owner_cpf_cnpj', $owner?->cpf_cnpj) }}" class="{{ $fieldClass }}" placeholder="CPF ou CNPJ" inputmode="numeric" @input="maskCpfCnpjField($event.target)" @blur="validateCpfCnpjField($event.target)">
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Telefones</label>
                            <button type="button" @click="ownerPhones.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(phone, index) in ownerPhones" :key="`owner-phone-${index}`">
                                <div class="flex gap-2">
                                    <input :name="`owner_phones[${index}]`" x-model="ownerPhones[index]" @input="maskPhoneField($event.target, 'ownerPhones', index)" class="{{ $fieldClass }} flex-1" placeholder="(27) 99723-2877" inputmode="numeric">
                                    <button type="button" @click="ownerPhones.splice(index,1); if(!ownerPhones.length) ownerPhones.push('')" class="{{ $secondaryButtonClass }}">Remover</button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">E-mails</label>
                            <button type="button" @click="ownerEmails.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(email, index) in ownerEmails" :key="`owner-email-${index}`">
                                <div class="flex gap-2">
                                    <input :name="`owner_emails[${index}]`" x-model="ownerEmails[index]" type="email" @blur="normalizeEmailField($event.target, 'ownerEmails', index)" class="{{ $fieldClass }} flex-1" placeholder="email@dominio.com" inputmode="email" autocomplete="off">
                                    <button type="button" @click="ownerEmails.splice(index,1); if(!ownerEmails.length) ownerEmails.push('')" class="{{ $secondaryButtonClass }}">Remover</button>
                                </div>
                            </template>
                        </div>
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
                <div class="mt-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações do proprietário</label>
                    <textarea name="owner_notes" rows="3" class="{{ $textareaClass }}">{{ old('owner_notes', $item?->owner_notes) }}</textarea>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Locatário</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Preencha apenas se houver locação vinculada.</span>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                        <input name="tenant_name" value="{{ old('tenant_name', $tenant?->display_name) }}" class="{{ $fieldClass }}" placeholder="Nome do locatário" data-normalize="title">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                        <input name="tenant_cpf_cnpj" value="{{ old('tenant_cpf_cnpj', $tenant?->cpf_cnpj) }}" class="{{ $fieldClass }}" placeholder="CPF ou CNPJ" inputmode="numeric" @input="maskCpfCnpjField($event.target)" @blur="validateCpfCnpjField($event.target)">
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Telefones</label>
                            <button type="button" @click="tenantPhones.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(phone, index) in tenantPhones" :key="`tenant-phone-${index}`">
                                <div class="flex gap-2">
                                    <input :name="`tenant_phones[${index}]`" x-model="tenantPhones[index]" @input="maskPhoneField($event.target, 'tenantPhones', index)" class="{{ $fieldClass }} flex-1" placeholder="(27) 99723-2877" inputmode="numeric">
                                    <button type="button" @click="tenantPhones.splice(index,1); if(!tenantPhones.length) tenantPhones.push('')" class="{{ $secondaryButtonClass }}">Remover</button>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">E-mails</label>
                            <button type="button" @click="tenantEmails.push('')" class="text-xs font-medium text-brand-600">+ adicionar</button>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(email, index) in tenantEmails" :key="`tenant-email-${index}`">
                                <div class="flex gap-2">
                                    <input :name="`tenant_emails[${index}]`" x-model="tenantEmails[index]" type="email" @blur="normalizeEmailField($event.target, 'tenantEmails', index)" class="{{ $fieldClass }} flex-1" placeholder="email@dominio.com" inputmode="email" autocomplete="off">
                                    <button type="button" @click="tenantEmails.splice(index,1); if(!tenantEmails.length) tenantEmails.push('')" class="{{ $secondaryButtonClass }}">Remover</button>
                                </div>
                            </template>
                        </div>
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
                <div class="mt-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações do locatário</label>
                    <textarea name="tenant_notes" rows="3" class="{{ $textareaClass }}">{{ old('tenant_notes', $item?->tenant_notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" data-file-preview>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos</h3>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel do anexo</label>
                        <select name="attachment_role" class="{{ $selectClass }}">
                            <option value="documento">Documento</option>
                            <option value="contrato">Contrato</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                            <i class="fa-solid fa-paperclip"></i>
                            <span>Escolher arquivos para anexar</span>
                            <input type="file" name="attachments[]" multiple class="sr-only" data-file-input data-multiple>
                        </label>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>Nenhum arquivo selecionado</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
                <div class="font-semibold text-gray-900 dark:text-white">Importação em massa</div>
                <p class="mt-2">A importação em lote fica na listagem de unidades. Você escolhe um condomínio, envia um CSV e o sistema cria ou atualiza as unidades daquele condomínio. Blocos e tipos de unidade que não existirem ainda podem ser criados automaticamente durante a importação.</p>
                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">Dica: baixe o CSV modelo na tela de listagem, preencha as colunas e exporte novamente em CSV.</div>
            </div>

            @if($mode === 'edit')
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Histórico de proprietário e locatário</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $partyHistory->count() }} registro(s)</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse($partyHistory as $history)
                            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ $history->party_type === 'owner' ? 'Proprietário' : 'Locatário' }}</span>
                                            @if(!$history->ended_at)
                                                <span class="rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-success-700 dark:bg-success-500/10 dark:text-success-300">Atual</span>
                                            @endif
                                        </div>
                                        <div class="mt-3 font-medium text-gray-900 dark:text-white">{{ $history->display_name_snapshot ?: 'Cadastro não identificado' }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $history->document_snapshot ?: 'Documento não informado' }}</div>
                                    </div>
                                    <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                                        <div>Início: {{ optional($history->started_at)->format('d/m/Y H:i') ?: '—' }}</div>
                                        <div class="mt-1">Fim: {{ optional($history->ended_at)->format('d/m/Y H:i') ?: 'Atual' }}</div>
                                        <div class="mt-1">Alterado por: {{ $history->changedBy?->name ?: 'Sistema' }}</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">Nenhuma troca de proprietário ou locatário foi registrada para esta unidade até o momento.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            @if($attachments->count())
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos cadastrados</h3>
                    <div class="mt-4 space-y-3">
                        @foreach($attachments as $attachment)
                            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $attachment->original_name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Papel: {{ ucfirst($attachment->file_role ?: 'documento') }}</div>
                                <div class="mt-2 flex gap-2">
                                    <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs text-white">Baixar</a>
                                    <button type="submit" form="attachment-delete-{{ $attachment->id }}" onclick="return confirm('Excluir este anexo?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs text-error-600 dark:text-error-300">Excluir</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</form>

@foreach($attachments as $attachment)
    <form id="attachment-delete-{{ $attachment->id }}" method="post" action="{{ route('clientes.attachments.delete', $attachment) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endforeach

<div class="mt-3 flex flex-wrap gap-3">
    <button type="submit" form="unidade-form" class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">{{ $mode === 'create' ? 'Cadastrar' : 'Salvar alterações' }}</button>

    @if($mode === 'edit')
        <form method="post" action="{{ route('clientes.unidades.delete', $item) }}">
            @csrf
            @method('DELETE')
            <button onclick="return confirm('Excluir esta unidade?')" class="rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600 dark:text-error-300">Excluir</button>
        </form>
    @endif
</div>
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
            init() {
                this.condominiumId = String(this.condominiumId || '');
                this.blockId = String(this.blockId || '');
                this.syncBlock();
                this.$nextTick(() => {
                    const blockSelect = this.$root.querySelector('select[name="block_id"]');
                    if (blockSelect && this.blockId) {
                        blockSelect.value = String(this.blockId);
                    }
                });
                this.ownerPhones = this.ownerPhones.map((value) => this.maskPhoneValue(value || ''));
                this.tenantPhones = this.tenantPhones.map((value) => this.maskPhoneValue(value || ''));
                this.ownerEmails = this.ownerEmails.map((value) => this.normalizeEmailValue(value || ''));
                this.tenantEmails = this.tenantEmails.map((value) => this.normalizeEmailValue(value || ''));
            },
            get blocks() {
                return this.blocksByCondo[this.condominiumId] || [];
            },
            syncBlock() {
                if (!this.blocks.find((block) => String(block.id) === String(this.blockId))) {
                    this.blockId = '';
                }
            },
            onlyDigits(value) {
                return String(value || '').replace(/\D/g, '');
            },
            maskPhoneValue(value) {
                let digits = this.onlyDigits(value);
                if (digits.length >= 12 && digits.startsWith('55')) {
                    digits = digits.slice(2);
                }
                digits = digits.slice(0, 11);

                if (digits.length <= 2) return digits ? `(${digits}` : '';
                if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
                if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
                return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
            },
            maskPhoneField(field, collection = null, index = null) {
                const masked = this.maskPhoneValue(field.value);
                field.value = masked;
                if (collection && index !== null && this[collection]) {
                    this[collection][index] = masked;
                }
            },
            maskCpfCnpjValue(value) {
                const digits = this.onlyDigits(value).slice(0, 14);
                if (digits.length <= 11) {
                    return digits
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                }
                return digits
                    .replace(/(\d{2})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1/$2')
                    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
            },
            validateCpf(digits) {
                if (!/^\d{11}$/.test(digits) || /(\d)\1{10}/.test(digits)) return false;
                for (let t = 9; t < 11; t++) {
                    let sum = 0;
                    for (let i = 0; i < t; i++) sum += Number(digits[i]) * ((t + 1) - i);
                    const digit = ((10 * sum) % 11) % 10;
                    if (Number(digits[t]) !== digit) return false;
                }
                return true;
            },
            validateCnpj(digits) {
                if (!/^\d{14}$/.test(digits) || /(\d)\1{13}/.test(digits)) return false;
                const calc = (base, factors) => {
                    const total = factors.reduce((sum, factor, index) => sum + Number(base[index]) * factor, 0);
                    const remainder = total % 11;
                    return remainder < 2 ? 0 : 11 - remainder;
                };
                const first = calc(digits, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
                const second = calc(digits, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
                return first === Number(digits[12]) && second === Number(digits[13]);
            },
            maskCpfCnpjField(field) {
                field.value = this.maskCpfCnpjValue(field.value);
                this.validateCpfCnpjField(field);
            },
            validateCpfCnpjField(field) {
                const digits = this.onlyDigits(field.value);
                if (!digits.length) {
                    field.setCustomValidity('');
                    return;
                }
                const valid = digits.length === 11 ? this.validateCpf(digits) : (digits.length === 14 ? this.validateCnpj(digits) : false);
                field.setCustomValidity(valid ? '' : 'Informe um CPF/CNPJ válido.');
            },
            normalizeEmailValue(value) {
                return String(value || '').trim().toLowerCase();
            },
            normalizeEmailField(field, collection = null, index = null) {
                const normalized = this.normalizeEmailValue(field.value);
                field.value = normalized;
                if (collection && index !== null && this[collection]) {
                    this[collection][index] = normalized;
                }
            },
        }
    }

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
