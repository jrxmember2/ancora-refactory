@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$case ? 'Editar OS de cobrança' : 'Nova OS de cobrança'" subtitle="Estruture a OS central com unidade, quotas, contatos, acordo, parcelas e faturamento.">
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

    <input type="hidden" name="unit_id" id="unit-id-hidden" value="{{ $formData['unit_id'] ?? '' }}">
    <input type="hidden" name="debtor_role" value="owner">

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais da OS</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Selecione o condomínio, depois o bloco quando existir, e por fim a unidade.</p>
            </div>
            <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 dark:border-brand-900/60 dark:bg-brand-500/10 dark:text-brand-200">
                O devedor da cobrança será sempre o <strong>proprietário da unidade</strong>.
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Condomínio</label>
                <select id="condominium-select" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach(($unitSelectorData['condominiums'] ?? []) as $condominium)
                        <option value="{{ $condominium['id'] }}">{{ $condominium['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div id="block-field-wrapper">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Bloco / torre</label>
                <select id="block-select" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                </select>
                <p id="block-field-hint" class="mt-2 text-xs text-gray-500 dark:text-gray-400">Selecione o bloco para liberar as unidades.</p>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Unidade</label>
                <select id="unit-select" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                </select>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Proprietário vinculado</div>
                <div id="owner-summary" class="mt-2 text-sm text-gray-800 dark:text-gray-100">Selecione a unidade para carregar o proprietário.</div>
                <div id="owner-contact-summary" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></div>
            </div>
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Histórico da unidade</div>
                <div id="unit-hint" class="mt-2 text-sm text-gray-800 dark:text-gray-100">Selecione a unidade para amarrar condomínio, bloco e histórico da cobrança.</div>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Locatário atual: {{ $formData['tenant_name'] ?: 'não informado' }}.</div>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de cobrança</label>
                <select name="charge_type" id="charge-type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    @foreach($chargeTypeLabels as $key => $label)
                        <option value="{{ $key }}" @selected(($formData['charge_type'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="judicial-case-field" class="md:col-span-2 {{ ($formData['charge_type'] ?? '') === 'judicial' ? '' : 'hidden' }}">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Número do processo</label>
                <input type="text" name="judicial_case_number" value="{{ $formData['judicial_case_number'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="5000000-00.2026.8.08.0000">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Base do cálculo</label>
                <input type="date" name="calc_base_date" value="{{ $formData['calc_base_date'] ?? now()->format('Y-m-d') }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor do acordo</label>
                <input type="text" data-money name="agreement_total" value="{{ $formData['agreement_total'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
            </div>
            <div class="xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Mensagem de alerta</label>
                <input type="text" name="alert_message" value="{{ $formData['alert_message'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Observação importante exibida ao abrir a OS">
            </div>
            <div class="xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações</label>
                <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ $formData['notes'] ?? '' }}</textarea>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Contatos para notificação</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Mantenha e-mails e telefones em linhas separadas para facilitar a operação.</p>
            </div>
        </div>

        <div class="mt-5 space-y-6">
            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">E-mails</h4>
                    <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="emails"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div class="space-y-3" data-repeater-container="emails">
                    @foreach($formRepeater['emails'] as $index => $row)
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 lg:grid-cols-[180px_minmax(0,1fr)_120px]" data-repeater-row>
                            <input type="text" name="emails[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <input type="email" name="emails[{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="email@dominio.com" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template="emails">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 lg:grid-cols-[180px_minmax(0,1fr)_120px]" data-repeater-row>
                        <input type="text" name="emails[__INDEX__][label]" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="email" name="emails[__INDEX__][value]" placeholder="email@dominio.com" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                    </div>
                </template>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Telefones / WhatsApp</h4>
                    <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="phones"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div class="space-y-3" data-repeater-container="phones">
                    @foreach($formRepeater['phones'] as $index => $row)
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 lg:grid-cols-[180px_minmax(0,1fr)_180px_120px]" data-repeater-row>
                            <input type="text" name="phones[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <input type="text" name="phones[{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="(27) 99999-9999" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <label class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="phones[{{ $index }}][is_whatsapp]" value="1" @checked(!empty($row['is_whatsapp']))>
                                WhatsApp
                            </label>
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template="phones">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 lg:grid-cols-[180px_minmax(0,1fr)_180px_120px]" data-repeater-row>
                        <input type="text" name="phones[__INDEX__][label]" placeholder="Rótulo" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="text" name="phones[__INDEX__][value]" placeholder="(27) 99999-9999" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <label class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                            <input type="checkbox" name="phones[__INDEX__][is_whatsapp]" value="1" checked>
                            WhatsApp
                        </label>
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Fluxo, entrada e honorários</h3>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                <input type="text" data-money name="entry_amount" value="{{ $formData['entry_amount'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Honorários</label>
                <input type="text" data-money name="fees_amount" value="{{ $formData['fees_amount'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="0,00">
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Quotas em aberto</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Campos ampliados para facilitar referência, vencimento e valores.</p>
            </div>
            <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="quotas"><i class="fa-solid fa-plus"></i></button>
        </div>
        <div class="space-y-3" data-repeater-container="quotas">
            @foreach($formRepeater['quotas'] as $index => $row)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-repeater-row>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-4">
                        <input type="text" name="quotas[{{ $index }}][reference_label]" value="{{ $row['reference_label'] ?? '' }}" placeholder="Competência / referência" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="date" name="quotas[{{ $index }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="text" data-money name="quotas[{{ $index }}][original_amount]" value="{{ $row['original_amount'] ?? '' }}" placeholder="Valor original" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="text" data-money name="quotas[{{ $index }}][updated_amount]" value="{{ $row['updated_amount'] ?? '' }}" placeholder="Valor atualizado" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                        <select name="quotas[{{ $index }}][status]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($quotaStatusLabels as $key => $label)
                                <option value="{{ $key }}" @selected(($row['status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="quotas[{{ $index }}][notes]" value="{{ $row['notes'] ?? '' }}" placeholder="Observação opcional da quota" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                    </div>
                </div>
            @endforeach
        </div>
        <template data-repeater-template="quotas">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-repeater-row>
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-4">
                    <input type="text" name="quotas[__INDEX__][reference_label]" placeholder="Competência / referência" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <input type="date" name="quotas[__INDEX__][due_date]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <input type="text" data-money name="quotas[__INDEX__][original_amount]" placeholder="Valor original" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <input type="text" data-money name="quotas[__INDEX__][updated_amount]" placeholder="Valor atualizado" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                    <select name="quotas[__INDEX__][status]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        @foreach($quotaStatusLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="quotas[__INDEX__][notes]" placeholder="Observação opcional da quota" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                </div>
            </div>
        </template>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Parcelas / vencimentos</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Linhas mais largas para descrição, valor e vencimento.</p>
            </div>
            <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="installments"><i class="fa-solid fa-plus"></i></button>
        </div>
        <div class="space-y-3" data-repeater-container="installments">
            @foreach($formRepeater['installments'] as $index => $row)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-repeater-row>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-5">
                        <input type="text" name="installments[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Descrição da parcela" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white xl:col-span-2">
                        <select name="installments[{{ $index }}][installment_type]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="parcela" @selected(($row['installment_type'] ?? '') === 'parcela')>Parcela</option>
                            <option value="entrada" @selected(($row['installment_type'] ?? '') === 'entrada')>Entrada</option>
                        </select>
                        <input type="number" min="1" name="installments[{{ $index }}][installment_number]" value="{{ $row['installment_number'] ?? '' }}" placeholder="#" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <input type="date" name="installments[{{ $index }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                        <input type="text" data-money name="installments[{{ $index }}][amount]" value="{{ $row['amount'] ?? '' }}" placeholder="Valor" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <select name="installments[{{ $index }}][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($installmentStatusLabels as $key => $label)
                                <option value="{{ $key }}" @selected(($row['status'] ?? '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                    </div>
                </div>
            @endforeach
        </div>
        <template data-repeater-template="installments">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-repeater-row>
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-5">
                    <input type="text" name="installments[__INDEX__][label]" placeholder="Descrição da parcela" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white xl:col-span-2">
                    <select name="installments[__INDEX__][installment_type]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <option value="parcela">Parcela</option>
                        <option value="entrada">Entrada</option>
                    </select>
                    <input type="number" min="1" name="installments[__INDEX__][installment_number]" placeholder="#" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <input type="date" name="installments[__INDEX__][due_date]" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                    <input type="text" data-money name="installments[__INDEX__][amount]" placeholder="Valor" class="h-11 rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <select name="installments[__INDEX__][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        @foreach($installmentStatusLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                </div>
            </div>
        </template>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Faturamento</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Deixado ao final do formulário para fechar a OS somente quando o processo operacional estiver concluído.</p>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
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
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">{{ $submitLabel }}</button>
        @if($case)
            <button type="button" disabled aria-disabled="true" title="Em breve será possível gerar o termo automaticamente por aqui." class="inline-flex cursor-not-allowed items-center gap-2 rounded-xl border border-brand-200 bg-brand-50/70 px-5 py-3 text-sm font-medium text-brand-400 opacity-70 dark:border-brand-900/60 dark:bg-brand-500/10 dark:text-brand-300/70">
                <i class="fa-solid fa-file-signature"></i>
                Gerar termo de acordo
            </button>
            <form method="post" action="{{ route('cobrancas.delete', $case) }}" onsubmit="return confirm('Excluir esta OS de cobrança?')">
                @csrf
                @method('DELETE')
                <button class="inline-flex items-center gap-2 rounded-xl border border-error-300 bg-white px-5 py-3 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-700/60 dark:bg-white/[0.03] dark:text-error-300">Excluir</button>
            </form>
        @endif
        <a href="{{ $case ? route('cobrancas.show', $case) : route('cobrancas.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const selectorData = @json($unitSelectorData ?? ['condominiums' => [], 'blocks' => [], 'units' => []]);
    const initialUnitId = String(document.getElementById('unit-id-hidden')?.value || '');

    const condominiumSelect = document.getElementById('condominium-select');
    const blockSelect = document.getElementById('block-select');
    const blockFieldWrapper = document.getElementById('block-field-wrapper');
    const blockFieldHint = document.getElementById('block-field-hint');
    const unitSelect = document.getElementById('unit-select');
    const unitIdHidden = document.getElementById('unit-id-hidden');
    const unitHint = document.getElementById('unit-hint');
    const ownerSummary = document.getElementById('owner-summary');
    const ownerContactSummary = document.getElementById('owner-contact-summary');
    const chargeType = document.getElementById('charge-type');
    const judicialField = document.getElementById('judicial-case-field');

    function moneyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function formatMoneyInput(input) {
        if (!input) return;
        const digits = moneyDigits(input.value);
        if (!digits) {
            input.value = '';
            return;
        }
        const amount = Number(digits) / 100;
        input.value = amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bindMoneyMask(scope = document) {
        scope.querySelectorAll('[data-money]').forEach((input) => {
            if (input.dataset.moneyBound === '1') return;
            input.dataset.moneyBound = '1';
            formatMoneyInput(input);
            input.addEventListener('input', () => formatMoneyInput(input));
            input.addEventListener('blur', () => formatMoneyInput(input));
        });
    }

    function updateChargeType() {
        if (!judicialField || !chargeType) return;
        judicialField.classList.toggle('hidden', chargeType.value !== 'judicial');
    }

    function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    }

    function getCondominiumBlocks(condominiumId) {
        return selectorData.blocks?.[String(condominiumId)] || [];
    }

    function condominiumHasBlocks(condominiumId) {
        return getCondominiumBlocks(condominiumId).length > 0;
    }

    function updateBlockFieldState(condominiumId) {
        const hasBlocks = condominiumHasBlocks(condominiumId);
        if (blockFieldWrapper) {
            blockFieldWrapper.classList.toggle('opacity-70', !hasBlocks);
        }
        if (blockFieldHint) {
            blockFieldHint.textContent = hasBlocks
                ? 'Selecione o bloco para liberar as unidades.'
                : 'Este condomínio não possui blocos cadastrados. As unidades são liberadas diretamente.';
        }
        return hasBlocks;
    }

    function populateBlocks(condominiumId, selectedBlockId = '') {
        resetSelect(blockSelect, 'Selecione');
        if (!blockSelect) return false;
        const blocks = getCondominiumBlocks(condominiumId);
        const hasBlocks = blocks.length > 0;

        blockSelect.disabled = !condominiumId || !hasBlocks;
        updateBlockFieldState(condominiumId);
        if (!hasBlocks) {
            return false;
        }

        blocks.forEach((block) => {
            const option = document.createElement('option');
            option.value = String(block.id);
            option.textContent = block.name;
            if (String(selectedBlockId) === String(block.id)) {
                option.selected = true;
            }
            blockSelect.appendChild(option);
        });

        return true;
    }

    function populateUnits(condominiumId, blockId = '', selectedUnitId = '') {
        if (!unitSelect) return;
        const hasBlocks = condominiumHasBlocks(condominiumId);
        resetSelect(unitSelect, hasBlocks && !blockId ? 'Selecione o bloco primeiro' : 'Selecione');

        if (!condominiumId) {
            unitSelect.disabled = true;
            return;
        }

        const condoUnits = selectorData.units?.[String(condominiumId)] || {};
        let units = [];

        if (hasBlocks) {
            if (!blockId) {
                unitSelect.disabled = true;
                return;
            }
            units = condoUnits[String(blockId)] || [];
        } else {
            unitSelect.disabled = false;
            units = Object.values(condoUnits).flat();
        }

        unitSelect.disabled = false;
        units.forEach((unit) => {
            const option = document.createElement('option');
            option.value = String(unit.id);
            option.textContent = unit.label;
            option.dataset.ownerName = unit.owner_name || '';
            option.dataset.ownerDocument = unit.owner_document || '';
            option.dataset.ownerEmail = unit.owner_email || '';
            option.dataset.ownerPhone = unit.owner_phone || '';
            option.dataset.unitNumber = unit.unit_number || '';
            option.dataset.blockId = unit.block_id || '';
            if (String(selectedUnitId) === String(unit.id)) {
                option.selected = true;
            }
            unitSelect.appendChild(option);
        });
    }

    function syncOwnerSummary() {
        const option = unitSelect?.options?.[unitSelect.selectedIndex];
        if (!option || !option.value) {
            unitIdHidden.value = '';
            ownerSummary.textContent = 'Selecione a unidade para carregar o proprietário.';
            ownerContactSummary.textContent = '';
            unitHint.textContent = 'Selecione a unidade para amarrar condomínio, bloco e histórico da cobrança.';
            return;
        }

        unitIdHidden.value = option.value;
        const ownerName = option.dataset.ownerName || 'Proprietário não informado';
        const ownerDocument = option.dataset.ownerDocument || 'Documento não informado';
        const ownerEmail = option.dataset.ownerEmail || 'E-mail não informado';
        const ownerPhone = option.dataset.ownerPhone || 'Telefone não informado';
        ownerSummary.textContent = `${ownerName} · ${ownerDocument}`;
        ownerContactSummary.textContent = `Contato principal: ${ownerEmail} | ${ownerPhone}`;
        unitHint.textContent = `Unidade selecionada: ${option.textContent}. O proprietário será usado como devedor desta OS.`;
    }

    function findUnitSelectionById(unitId) {
        const condominiums = selectorData.condominiums || [];
        for (const condominium of condominiums) {
            const condoUnits = selectorData.units?.[String(condominium.id)] || {};
            for (const [blockKey, units] of Object.entries(condoUnits)) {
                const found = (units || []).find((unit) => String(unit.id) === String(unitId));
                if (found) {
                    return {
                        condominiumId: String(condominium.id),
                        blockId: blockKey !== '0' ? String(blockKey) : '',
                        unitId: String(found.id),
                    };
                }
            }
        }
        return null;
    }

    function handleCondominiumChange(selectedBlockId = '', selectedUnitId = '') {
        const condominiumId = condominiumSelect?.value || '';
        const hasBlocks = populateBlocks(condominiumId, selectedBlockId);
        const effectiveBlockId = hasBlocks ? (selectedBlockId || blockSelect?.value || '') : '';
        populateUnits(condominiumId, effectiveBlockId, selectedUnitId);
        syncOwnerSummary();
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
            bindMoneyMask(container);
        });

        bindRemoveButtons(scope);
    }

    condominiumSelect?.addEventListener('change', () => handleCondominiumChange('', ''));
    blockSelect?.addEventListener('change', () => {
        populateUnits(condominiumSelect?.value || '', blockSelect?.value || '', '');
        syncOwnerSummary();
    });
    unitSelect?.addEventListener('change', syncOwnerSummary);
    chargeType?.addEventListener('change', updateChargeType);

    const selected = findUnitSelectionById(initialUnitId);
    if (selected && condominiumSelect) {
        condominiumSelect.value = selected.condominiumId;
        handleCondominiumChange(selected.blockId, selected.unitId);
        if (selected.blockId && blockSelect) {
            blockSelect.value = selected.blockId;
            populateUnits(selected.condominiumId, selected.blockId, selected.unitId);
        }
    } else {
        handleCondominiumChange('', '');
    }

    updateChargeType();
    initRepeater('emails');
    initRepeater('phones');
    initRepeater('quotas');
    initRepeater('installments');
    bindMoneyMask(document);
    syncOwnerSummary();
})();
</script>
@endpush
