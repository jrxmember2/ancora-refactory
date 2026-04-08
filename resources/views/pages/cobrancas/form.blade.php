@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$case ? 'Editar OS de cobrança' : 'Nova OS de cobrança'" subtitle="Estruture a OS central com unidade, devedor, quotas, contatos, acordo e parcelas.">
    @if($case)
        <a href="{{ route('cobrancas.show', $case) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar para a OS</a>
    @endif
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<form method="post" action="{{ $action }}" class="space-y-6">
    @csrf
    @if($case)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais da OS</h3>
                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Unidade vinculada</label>
                        <select id="unit-select" name="unit_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="">Selecione</option>
                            @foreach($units as $unit)
                                @php
                                    $label = ($unit->condominium?->name ?? 'Condomínio') . ' · ' . ($unit->block?->name ? $unit->block->name . ' · ' : '') . 'Unidade ' . $unit->unit_number;
                                @endphp
                                <option value="{{ $unit->id }}"
                                        data-owner="{{ $unit->owner?->display_name ?? '' }}"
                                        data-tenant="{{ $unit->tenant?->display_name ?? '' }}"
                                        @selected((int) ($formData['unit_id'] ?? 0) === (int) $unit->id)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <p id="unit-hint" class="mt-2 text-xs text-gray-500 dark:text-gray-400">Selecione a unidade para amarrar condomínio, bloco e histórico da cobrança.</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de devedor</label>
                        <select id="debtor-role" name="debtor_role" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="owner" @selected(($formData['debtor_role'] ?? 'owner') === 'owner')>Proprietário</option>
                            <option value="tenant" @selected(($formData['debtor_role'] ?? '') === 'tenant')>Locatário</option>
                            <option value="manual" @selected(($formData['debtor_role'] ?? '') === 'manual')>Informar manualmente</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de cobrança</label>
                        <select name="charge_type" id="charge-type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($chargeTypeLabels as $key => $label)
                                <option value="{{ $key }}" @selected(($formData['charge_type'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="manual-debtor-fields" class="md:col-span-2 grid grid-cols-1 gap-4 md:grid-cols-2 {{ ($formData['debtor_role'] ?? '') === 'manual' ? '' : 'hidden' }}">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome do devedor</label>
                            <input type="text" name="manual_debtor_name" value="{{ $formData['manual_debtor_name'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF/CNPJ</label>
                            <input type="text" name="manual_debtor_document" value="{{ $formData['manual_debtor_document'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail principal</label>
                            <input type="email" name="manual_debtor_email" value="{{ $formData['manual_debtor_email'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone principal</label>
                            <input type="text" name="manual_debtor_phone" value="{{ $formData['manual_debtor_phone'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div id="judicial-case-field" class="{{ ($formData['charge_type'] ?? '') === 'judicial' ? '' : 'hidden' }} md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Número do processo</label>
                        <input type="text" name="judicial_case_number" value="{{ $formData['judicial_case_number'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="5000000-00.2026.8.08.0000">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Base do cálculo</label>
                        <input type="date" name="calc_base_date" value="{{ $formData['calc_base_date'] ?? now()->format('Y-m-d') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor do acordo</label>
                        <input type="text" name="agreement_total" value="{{ $formData['agreement_total'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Mensagem de alerta</label>
                        <input type="text" name="alert_message" value="{{ $formData['alert_message'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Observação importante exibida ao abrir a OS">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações</label>
                        <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ $formData['notes'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contatos para notificação</h3>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Use o botão + para e-mails e WhatsApps adicionais.</div>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-6 xl:grid-cols-2">
                    <div>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">E-mails</h4>
                            <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="emails">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <div class="space-y-3" data-repeater-container="emails">
                            @foreach($formRepeater['emails'] as $index => $row)
                                <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 md:grid-cols-3" data-repeater-row>
                                    <input type="text" name="emails[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                    <input type="email" name="emails[{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="email@dominio.com" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                    <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                                </div>
                            @endforeach
                        </div>
                        <template data-repeater-template="emails">
                            <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 md:grid-cols-3" data-repeater-row>
                                <input type="text" name="emails[__INDEX__][label]" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                <input type="email" name="emails[__INDEX__][value]" placeholder="email@dominio.com" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                            </div>
                        </template>
                    </div>
                    <div>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Telefones</h4>
                            <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="phones">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <div class="space-y-3" data-repeater-container="phones">
                            @foreach($formRepeater['phones'] as $index => $row)
                                <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 md:grid-cols-4" data-repeater-row>
                                    <input type="text" name="phones[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                    <input type="text" name="phones[{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="(27) 99999-9999" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                    <label class="inline-flex h-11 items-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                        <input type="checkbox" name="phones[{{ $index }}][is_whatsapp]" value="1" @checked(!empty($row['is_whatsapp']))>
                                        WhatsApp
                                    </label>
                                    <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                                </div>
                            @endforeach
                        </div>
                        <template data-repeater-template="phones">
                            <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 md:grid-cols-4" data-repeater-row>
                                <input type="text" name="phones[__INDEX__][label]" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                <input type="text" name="phones[__INDEX__][value]" placeholder="(27) 99999-9999" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                <label class="inline-flex h-11 items-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                    <input type="checkbox" name="phones[__INDEX__][is_whatsapp]" value="1" checked>
                                    WhatsApp
                                </label>
                                <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Situação e faturamento</h3>
                <div class="mt-5 grid grid-cols-1 gap-4">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Etapa do fluxo</label>
                        <select name="workflow_stage" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($workflowStageLabels as $key => $label)
                                <option value="{{ $key }}" @selected(($formData['workflow_stage'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Situação</label>
                        <select name="situation" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($situationLabels as $key => $label)
                                <option value="{{ $key }}" @selected(($formData['situation'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status de faturamento</label>
                        <select name="billing_status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($billingStatusLabels as $key => $label)
                                <option value="{{ $key }}" @selected(($formData['billing_status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de faturamento</label>
                        <input type="date" name="billing_date" value="{{ $formData['billing_date'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status da entrada</label>
                        <select name="entry_status" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="">Selecione</option>
                            @foreach($entryStatusLabels as $key => $label)
                                <option value="{{ $key }}" @selected(($formData['entry_status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Vencimento da entrada</label>
                        <input type="date" name="entry_due_date" value="{{ $formData['entry_due_date'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor da entrada</label>
                        <input type="text" name="entry_amount" value="{{ $formData['entry_amount'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Honorários</label>
                        <input type="text" name="fees_amount" value="{{ $formData['fees_amount'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Quotas em aberto</h3>
                    <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="quotas"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div class="space-y-3" data-repeater-container="quotas">
                    @foreach($formRepeater['quotas'] as $index => $row)
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 xl:grid-cols-6" data-repeater-row>
                            <input type="text" name="quotas[{{ $index }}][reference_label]" value="{{ $row['reference_label'] ?? '' }}" placeholder="Competência / referência" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <input type="date" name="quotas[{{ $index }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <input type="text" name="quotas[{{ $index }}][original_amount]" value="{{ $row['original_amount'] ?? '' }}" placeholder="Valor original" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <input type="text" name="quotas[{{ $index }}][updated_amount]" value="{{ $row['updated_amount'] ?? '' }}" placeholder="Atualizado" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <select name="quotas[{{ $index }}][status]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                @foreach($quotaStatusLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(($row['status'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                            <div class="xl:col-span-6">
                                <input type="text" name="quotas[{{ $index }}][notes]" value="{{ $row['notes'] ?? '' }}" placeholder="Observação opcional da quota" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template="quotas">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 xl:grid-cols-6" data-repeater-row>
                        <input type="text" name="quotas[__INDEX__][reference_label]" placeholder="Competência / referência" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="date" name="quotas[__INDEX__][due_date]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="text" name="quotas[__INDEX__][original_amount]" placeholder="Valor original" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="text" name="quotas[__INDEX__][updated_amount]" placeholder="Atualizado" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <select name="quotas[__INDEX__][status]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($quotaStatusLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        <div class="xl:col-span-6">
                            <input type="text" name="quotas[__INDEX__][notes]" placeholder="Observação opcional da quota" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                    </div>
                </template>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Parcelas / vencimentos</h3>
                    <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="installments"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div class="space-y-3" data-repeater-container="installments">
                    @foreach($formRepeater['installments'] as $index => $row)
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 xl:grid-cols-6" data-repeater-row>
                            <input type="text" name="installments[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Descrição da parcela" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <select name="installments[{{ $index }}][installment_type]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                <option value="parcela" @selected(($row['installment_type'] ?? '') === 'parcela')>Parcela</option>
                                <option value="entrada" @selected(($row['installment_type'] ?? '') === 'entrada')>Entrada</option>
                            </select>
                            <input type="number" min="1" name="installments[{{ $index }}][installment_number]" value="{{ $row['installment_number'] ?? '' }}" placeholder="#" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <input type="date" name="installments[{{ $index }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <input type="text" name="installments[{{ $index }}][amount]" value="{{ $row['amount'] ?? '' }}" placeholder="Valor" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                            <div class="xl:col-span-6">
                                <select name="installments[{{ $index }}][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                    @foreach($installmentStatusLabels as $key => $label)
                                        <option value="{{ $key }}" @selected(($row['status'] ?? '') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template="installments">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 xl:grid-cols-6" data-repeater-row>
                        <input type="text" name="installments[__INDEX__][label]" placeholder="Descrição da parcela" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <select name="installments[__INDEX__][installment_type]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="parcela">Parcela</option>
                            <option value="entrada">Entrada</option>
                        </select>
                        <input type="number" min="1" name="installments[__INDEX__][installment_number]" placeholder="#" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="date" name="installments[__INDEX__][due_date]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="text" name="installments[__INDEX__][amount]" placeholder="Valor" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        <div class="xl:col-span-6">
                            <select name="installments[__INDEX__][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                @foreach($installmentStatusLabels as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">{{ $submitLabel }}</button>
        <a href="{{ $case ? route('cobrancas.show', $case) : route('cobrancas.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const debtorRole = document.getElementById('debtor-role');
    const manualFields = document.getElementById('manual-debtor-fields');
    const unitSelect = document.getElementById('unit-select');
    const unitHint = document.getElementById('unit-hint');
    const chargeType = document.getElementById('charge-type');
    const judicialField = document.getElementById('judicial-case-field');

    function updateDebtorState() {
        const role = debtorRole ? debtorRole.value : 'owner';
        if (manualFields) {
            manualFields.classList.toggle('hidden', role !== 'manual');
        }
        updateUnitHint();
    }

    function updateUnitHint() {
        if (!unitHint || !unitSelect) return;
        const option = unitSelect.options[unitSelect.selectedIndex];
        const owner = option ? option.dataset.owner || 'Não informado' : 'Não informado';
        const tenant = option ? option.dataset.tenant || 'Não informado' : 'Não informado';
        const role = debtorRole ? debtorRole.value : 'owner';
        if (!option || !option.value) {
            unitHint.textContent = 'Selecione a unidade para amarrar condomínio, bloco e histórico da cobrança.';
            return;
        }
        if (role === 'tenant') {
            unitHint.textContent = `Locatário atual da unidade: ${tenant}. Proprietário: ${owner}.`;
        } else if (role === 'manual') {
            unitHint.textContent = `Preenchimento manual habilitado. Proprietário cadastrado: ${owner}. Locatário cadastrado: ${tenant}.`;
        } else {
            unitHint.textContent = `Proprietário atual da unidade: ${owner}. Locatário: ${tenant}.`;
        }
    }

    function updateChargeType() {
        if (!judicialField || !chargeType) return;
        judicialField.classList.toggle('hidden', chargeType.value !== 'judicial');
    }

    function bindRemoveButtons(scope) {
        document.querySelectorAll(`[data-repeater-container="${scope}"] [data-repeater-remove]`).forEach((button) => {
            button.onclick = () => {
                const rows = document.querySelectorAll(`[data-repeater-container="${scope}"] [data-repeater-row]`);
                if (rows.length <= 1) return;
                button.closest('[data-repeater-row]')?.remove();
            };
        });
    }

    function initRepeater(scope) {
        const container = document.querySelector(`[data-repeater-container="${scope}"]`);
        const template = document.querySelector(`[data-repeater-template="${scope}"]`);
        const addButton = document.querySelector(`[data-repeater-add="${scope}"]`);
        if (!container || !template || !addButton) return;

        addButton.addEventListener('click', () => {
            const index = container.querySelectorAll('[data-repeater-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', index);
            container.insertAdjacentHTML('beforeend', html);
            bindRemoveButtons(scope);
        });

        bindRemoveButtons(scope);
    }

    debtorRole?.addEventListener('change', updateDebtorState);
    unitSelect?.addEventListener('change', updateUnitHint);
    chargeType?.addEventListener('change', updateChargeType);

    updateDebtorState();
    updateChargeType();
    initRepeater('emails');
    initRepeater('phones');
    initRepeater('quotas');
    initRepeater('installments');
})();
</script>
@endpush
