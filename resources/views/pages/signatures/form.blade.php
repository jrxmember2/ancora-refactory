@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $signers = collect($signers ?? [])->values();
    if ($signers->isEmpty()) {
        $signers = collect([[
            'name' => '',
            'email' => '',
            'phone' => '',
            'document_number' => '',
            'role_label' => '',
        ]]);
    }
@endphp

@section('content')
<x-ancora.section-header :title="$title" :subtitle="$subtitle">
    <div class="flex flex-wrap gap-3">
        <a href="{{ $cancelUrl }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr,0.8fr]">
    <form method="post" action="{{ $submitUrl }}" class="space-y-6">
        @csrf

        @if(!$providerConfigured)
            <div class="rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
                <div class="font-semibold">Assinafy ainda nao configurada</div>
                <div class="mt-2">Antes de enviar, configure: {{ implode(', ', $missingConfig) }}.</div>
                <div class="mt-3">
                    <a href="{{ route('contratos.settings.index') }}" class="font-medium underline">Abrir configuracoes de contratos</a>
                </div>
            </div>
        @endif

        @if($blockingReason)
            <div class="rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
                <div class="font-semibold">Documento ainda nao pronto para assinatura</div>
                <div class="mt-2">{{ $blockingReason }}</div>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Signatarios</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Defina quem vai receber o pedido de assinatura e acompanhe depois o status individual de cada pessoa.</p>
                </div>
                <button type="button" data-add-signer class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Adicionar signatario</button>
            </div>

            <div class="mt-5 space-y-4" data-signers-container>
                @foreach($signers as $index => $signer)
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800" data-signer-row>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                                <input data-field="name" name="signers[{{ $index }}][name]" value="{{ old('signers.'.$index.'.name', $signer['name'] ?? '') }}" class="{{ $inputClass }}" placeholder="Nome completo">
                                @error('signers.'.$index.'.name')
                                    <div class="mt-2 text-xs text-error-600 dark:text-error-300">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                                <input data-field="email" type="email" name="signers[{{ $index }}][email]" value="{{ old('signers.'.$index.'.email', $signer['email'] ?? '') }}" class="{{ $inputClass }}" placeholder="assinante@exemplo.com">
                                @error('signers.'.$index.'.email')
                                    <div class="mt-2 text-xs text-error-600 dark:text-error-300">{{ $message }}</div>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone / WhatsApp</label>
                                <input data-field="phone" name="signers[{{ $index }}][phone]" value="{{ old('signers.'.$index.'.phone', $signer['phone'] ?? '') }}" class="{{ $inputClass }}" placeholder="(00) 00000-0000">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                                <input data-field="document_number" name="signers[{{ $index }}][document_number]" value="{{ old('signers.'.$index.'.document_number', $signer['document_number'] ?? '') }}" class="{{ $inputClass }}" placeholder="Documento do signatario">
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel no documento</label>
                                <div class="flex gap-3">
                                    <input data-field="role_label" name="signers[{{ $index }}][role_label]" value="{{ old('signers.'.$index.'.role_label', $signer['role_label'] ?? '') }}" class="{{ $inputClass }}" placeholder="Ex.: Cliente, Sindico, Devedor(a)">
                                    <button type="button" data-remove-signer class="shrink-0 rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-800 dark:text-error-300">Remover</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('signers')
                <div class="mt-3 text-xs text-error-600 dark:text-error-300">{{ $message }}</div>
            @enderror
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Mensagem aos signatarios</h3>
            <textarea name="signer_message" rows="4" class="mt-4 {{ $textareaClass }}" placeholder="Mensagem opcional enviada junto com o pedido de assinatura.">{{ $signerMessage }}</textarea>
            @if(!empty($messageVariables))
                <div class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/40">
                    <div class="text-sm font-medium text-gray-800 dark:text-gray-100">Variaveis aceitas nesta mensagem</div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se voce digitar alguma destas chaves, o sistema substitui automaticamente antes de enviar para a Assinafy.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($messageVariables as $variable)
                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" title="{{ $variable['label'] }}">
                                {{ $variable['token'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ $cancelUrl }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</a>
            <button @disabled(!$providerConfigured || !$canSubmit) class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50">{{ $submitLabel }}</button>
        </div>
    </form>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Conferencia do envio</h3>
            <div class="mt-5 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                @if($mode === 'contract')
                    <div><span class="text-gray-500">Contrato:</span> {{ $signable->code ?: $signable->title }}</div>
                    <div><span class="text-gray-500">Titulo:</span> {{ $signable->title }}</div>
                    <div><span class="text-gray-500">Cliente:</span> {{ $signable->client?->display_name ?: 'Nao informado' }}</div>
                    <div><span class="text-gray-500">Condominio:</span> {{ $signable->condominium?->name ?: 'Nao aplicavel' }}</div>
                    <div><span class="text-gray-500">PDF final:</span> {{ $signable->final_pdf_path ? 'Ja gerado' : 'Sera gerado automaticamente no envio' }}</div>
                @else
                    <div><span class="text-gray-500">OS:</span> {{ $signable->os_number }}</div>
                    <div><span class="text-gray-500">Condominio:</span> {{ $signable->condominium?->name ?: 'Nao informado' }}</div>
                    <div><span class="text-gray-500">Unidade:</span> {{ $signable->unit?->unit_number ?: 'Nao informada' }}</div>
                    <div><span class="text-gray-500">Devedor(a):</span> {{ $signable->debtor_name_snapshot ?: 'Nao informado' }}</div>
                    <div><span class="text-gray-500">Termo salvo:</span> {{ !empty($termSaved) ? 'Sim' : 'Nao' }}</div>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Como vai funcionar</h3>
            <ol class="mt-5 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                <li>1. O sistema envia o PDF para a Assinafy e cria o pedido de assinatura.</li>
                <li>2. Cada signatario recebe o link individual de assinatura.</li>
                <li>3. O painel dentro do contrato ou da OS mostra quem visualizou, assinou ou recusou.</li>
                <li>4. Quando todos concluirem, o documento assinado e o pacote de certificado ficam disponiveis para download.</li>
            </ol>
        </div>
    </div>
</div>

<template id="signature-signer-template">
    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800" data-signer-row>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input class="{{ $inputClass }}" data-field="name" placeholder="Nome completo">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                <input type="email" class="{{ $inputClass }}" data-field="email" placeholder="assinante@exemplo.com">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone / WhatsApp</label>
                <input class="{{ $inputClass }}" data-field="phone" placeholder="(00) 00000-0000">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF / CNPJ</label>
                <input class="{{ $inputClass }}" data-field="document_number" placeholder="Documento do signatario">
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Papel no documento</label>
                <div class="flex gap-3">
                    <input class="{{ $inputClass }}" data-field="role_label" placeholder="Ex.: Cliente, Sindico, Devedor(a)">
                    <button type="button" data-remove-signer class="shrink-0 rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-800 dark:text-error-300">Remover</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('[data-signers-container]');
    const template = document.getElementById('signature-signer-template');
    const addButton = document.querySelector('[data-add-signer]');

    if (!container || !template || !addButton) {
        return;
    }

    const reindex = () => {
        Array.from(container.querySelectorAll('[data-signer-row]')).forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach((field) => {
                field.name = `signers[${index}][${field.dataset.field}]`;
            });
        });
    };

    const clearRow = (row) => {
        row.querySelectorAll('input').forEach((field) => {
            field.value = '';
        });
    };

    addButton.addEventListener('click', () => {
        const clone = template.content.firstElementChild.cloneNode(true);
        container.appendChild(clone);
        reindex();
    });

    container.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-signer]');
        if (!button) {
            return;
        }

        const row = button.closest('[data-signer-row]');
        if (!row) {
            return;
        }

        const rows = container.querySelectorAll('[data-signer-row]');
        if (rows.length <= 1) {
            clearRow(row);
            return;
        }

        row.remove();
        reindex();
    });

    reindex();
});
</script>
@endsection
