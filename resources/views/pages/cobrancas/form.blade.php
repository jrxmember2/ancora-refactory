@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$case ? 'Editar OS de cobrança' : 'Nova OS de cobrança'" subtitle="Estruture a OS central com unidade, quotas, contatos, acordo, parcelas e faturamento.">
    @if($case)
        <a href="{{ route('cobrancas.show', $case) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar para a OS</a>
    @endif
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<form method="post" action="{{ $action }}" class="space-y-6" id="cobranca-form">
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
            <div id="block-field-wrapper" class="hidden">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Bloco / torre</label>
                <select id="block-select" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-white" disabled>
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
                            <div>
                                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Rótulo</label>
                                <input type="text" name="emails[{{ $index }}][label]" value="{{ $row['label'] ?? ($index === 0 ? 'Principal' : '') }}" placeholder="{{ $index === 0 ? 'Principal' : 'Rótulo' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">E-mail</label>
                                <input type="email" name="emails[{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="email@dominio.com" autocomplete="off" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                            <div class="flex flex-col justify-end">
                                <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                                <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template="emails">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 lg:grid-cols-[180px_minmax(0,1fr)_120px]" data-repeater-row>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Rótulo</label>
                            <input type="text" name="emails[__INDEX__][label]" placeholder="Rótulo" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">E-mail</label>
                            <input type="email" name="emails[__INDEX__][value]" placeholder="email@dominio.com" autocomplete="off" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div class="flex flex-col justify-end">
                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        </div>
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
                            <div>
                                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Rótulo</label>
                                <input type="text" name="phones[{{ $index }}][label]" value="{{ $row['label'] ?? ($index === 0 ? 'Principal' : '') }}" placeholder="{{ $index === 0 ? 'Principal' : 'Rótulo' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Telefone</label>
                                <input type="text" data-phone name="phones[{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="(27) 99999-9999" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Canal</label>
                                <label class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                    <input type="checkbox" name="phones[{{ $index }}][is_whatsapp]" value="1" @checked(!empty($row['is_whatsapp']))>
                                    WhatsApp
                                </label>
                            </div>
                            <div class="flex flex-col justify-end">
                                <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                                <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template="phones">
                    <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800 lg:grid-cols-[180px_minmax(0,1fr)_180px_120px]" data-repeater-row>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Rótulo</label>
                            <input type="text" name="phones[__INDEX__][label]" placeholder="Rótulo" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Telefone</label>
                            <input type="text" data-phone name="phones[__INDEX__][value]" placeholder="(27) 99999-9999" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Canal</label>
                            <label class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="phones[__INDEX__][is_whatsapp]" value="1" checked>
                                WhatsApp
                            </label>
                        </div>
                        <div class="flex flex-col justify-end">
                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        </div>
                    </div>
                </template>
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
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Competência / referência</label>
                            <input type="text" data-reference-period name="quotas[{{ $index }}][reference_label]" value="{{ $row['reference_label'] ?? '' }}" placeholder="mm/aaaa" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vencimento</label>
                            <input type="date" name="quotas[{{ $index }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor original</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-500 dark:text-gray-400">R$</span>
                                <input type="text" data-money name="quotas[{{ $index }}][original_amount]" value="{{ $row['original_amount'] ?? '' }}" placeholder="0,00" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor atualizado</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-500 dark:text-gray-400">R$</span>
                                <input type="text" data-money name="quotas[{{ $index }}][updated_amount]" value="{{ $row['updated_amount'] ?? '' }}" placeholder="0,00" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo da quota</label>
                            <select name="quotas[{{ $index }}][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                @foreach($quotaStatusLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(($row['status'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Observação</label>
                            <input type="text" name="quotas[{{ $index }}][notes]" value="{{ $row['notes'] ?? '' }}" placeholder="Observação opcional da quota" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div class="flex flex-col justify-end">
                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <template data-repeater-template="quotas">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-repeater-row>
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Competência / referência</label>
                        <input type="text" data-reference-period name="quotas[__INDEX__][reference_label]" placeholder="mm/aaaa" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vencimento</label>
                        <input type="date" name="quotas[__INDEX__][due_date]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor original</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-500 dark:text-gray-400">R$</span>
                            <input type="text" data-money name="quotas[__INDEX__][original_amount]" placeholder="0,00" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor atualizado</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-500 dark:text-gray-400">R$</span>
                            <input type="text" data-money name="quotas[__INDEX__][updated_amount]" placeholder="0,00" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo da quota</label>
                        <select name="quotas[__INDEX__][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($quotaStatusLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Observação</label>
                        <input type="text" name="quotas[__INDEX__][notes]" placeholder="Observação opcional da quota" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div class="flex flex-col justify-end">
                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="mb-3 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Parcelas / vencimentos</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Linhas mais largas para descrição, valor e vencimento.</p>
            </div>
            <div class="flex flex-col gap-2 lg:items-end">
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="installments-auto-split" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">Divisão automática</button>
                    <button type="button" id="installments-single" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.04]">Parcela única</button>
                    <button type="button" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10" data-repeater-add="installments"><i class="fa-solid fa-plus"></i></button>
                </div>
                <div id="installments-balance-card" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-950/30 dark:text-gray-200">
                    <span class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Valor restante</span>
                    <strong id="installments-balance" class="ml-2 text-gray-900 dark:text-white">R$ 0,00</strong>
                    <span id="installments-balance-hint" class="ml-2 text-xs text-gray-500 dark:text-gray-400">Plano fechado.</span>
                </div>
            </div>
        </div>
        <div class="mb-5 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/30">
            <div class="flex flex-col gap-1">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Situação, entrada e honorários</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">Etapa e situação foram consolidadas para deixar o fluxo mais objetivo. A entrada e os honorários ficam junto do plano de pagamento.</p>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Situação da OS</label>
                    <select name="workflow_stage" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-transparent dark:text-white">
                        @foreach($workflowStageLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($formData['workflow_stage'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status da entrada</label>
                    <select name="entry_status" id="entry-status-select" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-transparent dark:text-white">
                        <option value="">Selecione</option>
                        @foreach($entryStatusLabels as $key => $label)
                            <option value="{{ $key }}" @selected(($formData['entry_status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                        <option value="__custom" @selected(($formData['entry_status'] ?? '') === '__custom')>Outro</option>
                    </select>
                </div>
                <div id="entry-status-custom-wrapper" class="{{ ($formData['entry_status'] ?? '') === '__custom' ? '' : 'hidden' }}">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Qual status?</label>
                    <input type="text" name="entry_status_custom" value="{{ $formData['entry_status_custom'] ?? '' }}" maxlength="40" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-transparent dark:text-white" placeholder="Ex.: PIX confirmado">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Vencimento da entrada</label>
                    <input type="date" name="entry_due_date" value="{{ $formData['entry_due_date'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-transparent dark:text-white">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor da entrada</label>
                    <input type="text" data-money name="entry_amount" value="{{ $formData['entry_amount'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-transparent dark:text-white" placeholder="0,00">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Honorários</label>
                    <input type="text" data-money name="fees_amount" value="{{ $formData['fees_amount'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-transparent dark:text-white" placeholder="0,00">
                </div>
            </div>
        </div>
        <div class="space-y-3" data-repeater-container="installments">
            @foreach($formRepeater['installments'] as $index => $row)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-repeater-row>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-5">
                        <div class="xl:col-span-2">
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Descrição da parcela</label>
                            <input type="text" name="installments[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Descrição da parcela" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo</label>
                            <select name="installments[{{ $index }}][installment_type]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                <option value="parcela" @selected(($row['installment_type'] ?? '') === 'parcela')>Parcela</option>
                                <option value="entrada" @selected(($row['installment_type'] ?? '') === 'entrada')>Entrada</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Número</label>
                            <input type="number" min="1" name="installments[{{ $index }}][installment_number]" value="{{ $row['installment_number'] ?? '' }}" placeholder="#" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vencimento</label>
                            <input type="date" name="installments[{{ $index }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-500 dark:text-gray-400">R$</span>
                                <input type="text" data-money name="installments[{{ $index }}][amount]" value="{{ $row['amount'] ?? '' }}" placeholder="0,00" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</label>
                            <select name="installments[{{ $index }}][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                @foreach($installmentStatusLabels as $key => $label)
                                    <option value="{{ $key }}" @selected(($row['status'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col justify-end">
                            <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                            <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <template data-repeater-template="installments">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-repeater-row>
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 xl:grid-cols-5">
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Descrição da parcela</label>
                        <input type="text" name="installments[__INDEX__][label]" placeholder="Descrição da parcela" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo</label>
                        <select name="installments[__INDEX__][installment_type]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="parcela">Parcela</option>
                            <option value="entrada">Entrada</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Número</label>
                        <input type="number" min="1" name="installments[__INDEX__][installment_number]" placeholder="#" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vencimento</label>
                        <input type="date" name="installments[__INDEX__][due_date]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[220px_minmax(0,1fr)_120px]">
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm text-gray-500 dark:text-gray-400">R$</span>
                            <input type="text" data-money name="installments[__INDEX__][amount]" placeholder="0,00" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent pl-11 pr-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</label>
                        <select name="installments[__INDEX__][status]" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            @foreach($installmentStatusLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-col justify-end">
                        <span class="mb-2 block text-xs font-medium uppercase tracking-wide text-transparent select-none">Ação</span>
                        <button type="button" class="rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-repeater-remove>Remover</button>
                    </div>
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
        <button type="submit" form="cobranca-form" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">{{ $submitLabel }}</button>
        @if($case)
            @if($agreementPaymentError ?? null)
                <span title="{{ $agreementPaymentError }}" class="inline-flex cursor-not-allowed items-center gap-2 rounded-xl border border-gray-200 bg-gray-100 px-5 py-3 text-sm font-medium text-gray-400 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-500">
                    <i class="fa-solid fa-file-signature"></i>
                    Gerar termo de acordo
                </span>
            @else
                <a href="{{ route('cobrancas.agreement.edit', $case) }}" class="inline-flex items-center gap-2 rounded-xl border border-brand-300 bg-brand-50 px-5 py-3 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">
                    <i class="fa-solid fa-file-signature"></i>
                    Gerar termo de acordo
                </a>
            @endif
            <button type="submit" form="delete-cobranca-form" onclick="return confirm('Excluir esta OS de cobrança?')" class="inline-flex items-center gap-2 rounded-xl border border-error-300 bg-white px-5 py-3 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-700/60 dark:bg-white/[0.03] dark:text-error-300">Excluir</button>
        @endif
        <a href="{{ $case ? route('cobrancas.show', $case) : route('cobrancas.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</a>
    </div>
</form>
@if($case)
    <form id="delete-cobranca-form" method="post" action="{{ route('cobrancas.delete', $case) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif

<dialog id="auto-split-modal" class="fixed inset-0 m-auto w-full max-w-lg rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <form id="auto-split-form" method="dialog">
        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Divisão automática</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informe o total de parcelas incluindo a entrada. O sistema divide o valor do acordo e joga eventual diferença de centavos na última parcela.</p>
        </div>
        <div class="space-y-4 px-6 py-5">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantidade total, incluindo entrada</label>
                <input type="number" id="auto-split-count" min="2" step="1" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Ex.: 4">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Vencimento inicial</label>
                <input type="date" id="auto-split-start-date" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">A entrada usa esta data; as demais parcelas vencem mensalmente a partir dela.</p>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            <button type="button" id="auto-split-cancel" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</button>
            <button type="submit" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">Preencher parcelas</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
(function () {
    const selectorData = @json($unitSelectorData ?? ['condominiums' => [], 'blocks' => [], 'units' => []]);
    const initialUnitId = String(document.getElementById('unit-id-hidden')?.value || '');
    const isCreateMode = {{ $case ? 'false' : 'true' }};

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
    const form = document.getElementById('cobranca-form');
    const agreementTotalInput = form?.querySelector('[name="agreement_total"]');
    const entryDueDateInput = form?.querySelector('[name="entry_due_date"]');
    const entryAmountInput = form?.querySelector('[name="entry_amount"]');
    const entryStatusInput = form?.querySelector('[name="entry_status"]');
    const entryStatusCustomWrapper = document.getElementById('entry-status-custom-wrapper');
    const entryStatusCustomInput = form?.querySelector('[name="entry_status_custom"]');
    const installmentsBalance = document.getElementById('installments-balance');
    const installmentsBalanceHint = document.getElementById('installments-balance-hint');
    const autoSplitButton = document.getElementById('installments-auto-split');
    const singleInstallmentButton = document.getElementById('installments-single');
    const autoSplitModal = document.getElementById('auto-split-modal');
    const autoSplitForm = document.getElementById('auto-split-form');
    const autoSplitCount = document.getElementById('auto-split-count');
    const autoSplitStartDate = document.getElementById('auto-split-start-date');
    const autoSplitCancel = document.getElementById('auto-split-cancel');

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

    function moneyCentsFromValue(value) {
        const digits = moneyDigits(value);
        return digits ? Number(digits) : 0;
    }

    function moneyCentsFromInput(input) {
        return moneyCentsFromValue(input?.value || '');
    }

    function formatCentsValue(cents) {
        return (Math.max(0, cents) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatCentsLabel(cents) {
        return `R$ ${formatCentsValue(Math.abs(cents))}`;
    }

    function installmentRows() {
        return Array.from(document.querySelectorAll('[data-repeater-container="installments"] [data-repeater-row]'));
    }

    function installmentsTotalCents() {
        return installmentRows().reduce((total, row) => {
            return total + moneyCentsFromInput(row.querySelector('input[name$="[amount]"]'));
        }, 0);
    }

    function paymentPlanDifferenceCents() {
        const agreementTotal = moneyCentsFromInput(agreementTotalInput);
        const covered = moneyCentsFromInput(entryAmountInput) + installmentsTotalCents();
        return agreementTotal - covered;
    }

    function updatePaymentBalance() {
        if (!installmentsBalance || !installmentsBalanceHint) return;

        const agreementTotal = moneyCentsFromInput(agreementTotalInput);
        const difference = paymentPlanDifferenceCents();
        installmentsBalance.textContent = formatCentsLabel(difference);

        if (agreementTotal <= 0) {
            installmentsBalanceHint.textContent = 'Informe o valor do acordo.';
            installmentsBalanceHint.className = 'ml-2 text-xs text-gray-500 dark:text-gray-400';
            return;
        }

        if (difference === 0) {
            installmentsBalanceHint.textContent = 'Plano fechado.';
            installmentsBalanceHint.className = 'ml-2 text-xs text-success-600 dark:text-success-400';
            return;
        }

        if (difference > 0) {
            installmentsBalanceHint.textContent = 'Faltam parcelas.';
            installmentsBalanceHint.className = 'ml-2 text-xs text-warning-700 dark:text-warning-300';
            return;
        }

        installmentsBalanceHint.textContent = 'Valor excedente.';
        installmentsBalanceHint.className = 'ml-2 text-xs text-error-600 dark:text-error-400';
    }

    function bindMoneyMask(scope = document) {
        scope.querySelectorAll('[data-money]').forEach((input) => {
            if (input.dataset.moneyBound === '1') return;
            input.dataset.moneyBound = '1';
            formatMoneyInput(input);
            input.addEventListener('input', () => {
                formatMoneyInput(input);
                updatePaymentBalance();
            });
            input.addEventListener('blur', () => {
                formatMoneyInput(input);
                updatePaymentBalance();
            });
        });
    }

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function formatPhoneValue(value) {
        let digits = onlyDigits(value);
        if (digits.length >= 12 && digits.startsWith('55')) {
            digits = digits.slice(2);
        }
        digits = digits.slice(0, 11);
        if (digits.length <= 2) return digits ? `(${digits}` : '';
        if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
        if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
    }

    function bindPhoneMask(scope = document) {
        scope.querySelectorAll('[data-phone]').forEach((input) => {
            if (input.dataset.phoneBound === '1') return;
            input.dataset.phoneBound = '1';
            input.value = formatPhoneValue(input.value);
            input.addEventListener('input', () => {
                input.value = formatPhoneValue(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = formatPhoneValue(input.value);
            });
        });
    }

    function formatReferencePeriodValue(value) {
        const digits = onlyDigits(value).slice(0, 6);
        if (digits.length <= 2) return digits;
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    function bindReferencePeriodMask(scope = document) {
        scope.querySelectorAll('[data-reference-period]').forEach((input) => {
            if (input.dataset.referenceBound === '1') return;
            input.dataset.referenceBound = '1';
            input.value = formatReferencePeriodValue(input.value);
            input.addEventListener('input', () => {
                input.value = formatReferencePeriodValue(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = formatReferencePeriodValue(input.value);
            });
        });
    }

    function normalizeEmailValue(value) {
        return String(value || '').trim().toLowerCase();
    }

    function bindEmailNormalization(scope = document) {
        scope.querySelectorAll('input[type="email"]').forEach((input) => {
            if (input.dataset.emailBound === '1') return;
            input.dataset.emailBound = '1';
            input.addEventListener('blur', () => {
                input.value = normalizeEmailValue(input.value);
            });
        });
    }

    function updateChargeType() {
        if (!judicialField || !chargeType) return;
        judicialField.classList.toggle('hidden', chargeType.value !== 'judicial');
    }

    function updateEntryStatusCustomField() {
        const isCustom = entryStatusInput?.value === '__custom';
        entryStatusCustomWrapper?.classList.toggle('hidden', !isCustom);
        if (!isCustom && entryStatusCustomInput) {
            entryStatusCustomInput.value = '';
        }
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
            blockFieldWrapper.classList.toggle('hidden', !condominiumId || !hasBlocks);
            blockFieldWrapper.classList.toggle('opacity-70', false);
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
            option.dataset.ownerEmails = JSON.stringify(unit.owner_emails || []);
            option.dataset.ownerPhones = JSON.stringify(unit.owner_phones || []);
            option.dataset.unitNumber = unit.unit_number || '';
            option.dataset.blockId = unit.block_id || '';
            if (String(selectedUnitId) === String(unit.id)) {
                option.selected = true;
            }
            unitSelect.appendChild(option);
        });
    }


    function parseDatasetArray(value) {
        try {
            const parsed = JSON.parse(value || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function rebuildRepeater(scope, rows) {
        const container = document.querySelector(`[data-repeater-container="${scope}"]`);
        const template = document.querySelector(`[data-repeater-template="${scope}"]`);
        if (!container || !template) return;

        const safeRows = rows.length ? rows : (scope === 'phones'
            ? [{ label: 'Principal', value: '', is_whatsapp: true }]
            : [{ label: 'Principal', value: '' }]);

        container.innerHTML = '';
        safeRows.forEach((row, index) => {
            const html = template.innerHTML.replaceAll('__INDEX__', index);
            container.insertAdjacentHTML('beforeend', html);
            const inserted = container.lastElementChild;
            if (!inserted) return;
            const labelInput = inserted.querySelector('input[name$="[label]"]');
            const valueInput = inserted.querySelector('input[name$="[value]"]');
            const whatsappInput = inserted.querySelector('input[type="checkbox"]');
            if (labelInput) {
                labelInput.value = row.label || (index === 0 ? 'Principal' : '');
                labelInput.placeholder = index === 0 ? 'Principal' : 'Rótulo';
            }
            if (valueInput) valueInput.value = row.value || '';
            if (whatsappInput) whatsappInput.checked = !!row.is_whatsapp;
        });

        bindRemoveButtons(scope);
        bindMoneyMask(container);
        bindPhoneMask(container);
        bindReferencePeriodMask(container);
        bindEmailNormalization(container);
    }

    function seedNotificationContactsFromSelectedUnit() {
        if (!isCreateMode) return;
        const option = unitSelect?.options?.[unitSelect.selectedIndex];
        if (!option || !option.value) return;

        const ownerEmails = parseDatasetArray(option.dataset.ownerEmails)
            .map((value, index) => ({ label: index === 0 ? 'Principal' : '', value: normalizeEmailValue(value) }))
            .filter((row) => row.value !== '');
        const ownerPhones = parseDatasetArray(option.dataset.ownerPhones)
            .map((value, index) => ({ label: index === 0 ? 'Principal' : '', value: formatPhoneValue(value), is_whatsapp: true }))
            .filter((row) => row.value !== '');

        rebuildRepeater('emails', ownerEmails);
        rebuildRepeater('phones', ownerPhones);
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

    function reindexRepeater(scope) {
        document.querySelectorAll(`[data-repeater-container="${scope}"] [data-repeater-row]`).forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(new RegExp(`${scope}\\[\\d+\\]`), `${scope}[${index}]`);
            });
        });
    }

    function setInstallmentRowValues(rowElement, row) {
        rowElement.querySelector('input[name$="[label]"]').value = row.label || '';
        rowElement.querySelector('select[name$="[installment_type]"]').value = row.installment_type || 'parcela';
        rowElement.querySelector('input[name$="[installment_number]"]').value = row.installment_number || '';
        rowElement.querySelector('input[name$="[due_date]"]').value = row.due_date || '';
        rowElement.querySelector('input[name$="[amount]"]').value = row.amount_cents !== undefined ? formatCentsValue(row.amount_cents) : (row.amount || '');
        rowElement.querySelector('select[name$="[status]"]').value = row.status || 'pendente';
    }

    function rebuildInstallmentRows(rows) {
        const container = document.querySelector('[data-repeater-container="installments"]');
        const template = document.querySelector('[data-repeater-template="installments"]');
        if (!container || !template) return;

        container.innerHTML = '';
        (rows.length ? rows : [{ label: '', installment_type: 'parcela', installment_number: '', due_date: '', amount_cents: 0, status: 'pendente' }]).forEach((row, index) => {
            const html = template.innerHTML.replaceAll('__INDEX__', index);
            container.insertAdjacentHTML('beforeend', html);
            const inserted = container.lastElementChild;
            if (inserted) {
                setInstallmentRowValues(inserted, row);
            }
        });

        bindRemoveButtons('installments');
        bindMoneyMask(container);
        updatePaymentBalance();
    }

    function dateInputValue(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function todayInputValue() {
        return dateInputValue(new Date());
    }

    function addMonthsToInputDate(value, offset) {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) {
            return '';
        }

        const [year, month, day] = parts;
        const target = new Date(year, month - 1 + offset, 1);
        const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
        target.setDate(Math.min(day, lastDay));
        return dateInputValue(target);
    }

    function openAutoSplitModal() {
        const agreementTotal = moneyCentsFromInput(agreementTotalInput);
        if (agreementTotal <= 0) {
            alert('Informe o valor do acordo antes de usar a divisão automática.');
            agreementTotalInput?.focus();
            return;
        }

        const filledInstallments = installmentRows().filter((row) => {
            return moneyCentsFromInput(row.querySelector('input[name$="[amount]"]')) > 0;
        }).length;
        const suggestedCount = Math.max(2, filledInstallments + (moneyCentsFromInput(entryAmountInput) > 0 ? 1 : 0));
        if (autoSplitCount) autoSplitCount.value = suggestedCount;
        if (autoSplitStartDate) autoSplitStartDate.value = entryDueDateInput?.value || todayInputValue();
        autoSplitModal?.showModal();
    }

    function applyAutoSplit() {
        const agreementTotal = moneyCentsFromInput(agreementTotalInput);
        const count = Math.max(0, parseInt(autoSplitCount?.value || '0', 10));
        const startDate = autoSplitStartDate?.value || '';

        if (agreementTotal <= 0) {
            alert('Informe o valor do acordo antes de dividir.');
            return;
        }
        if (count < 2) {
            alert('Informe pelo menos 2 parcelas, contando a entrada.');
            autoSplitCount?.focus();
            return;
        }
        if (!startDate) {
            alert('Informe o vencimento inicial.');
            autoSplitStartDate?.focus();
            return;
        }

        const base = Math.floor(agreementTotal / count);
        if (base <= 0) {
            alert('A quantidade de parcelas é maior que o valor disponível em centavos.');
            return;
        }

        const amounts = Array(count).fill(base);
        amounts[count - 1] += agreementTotal - (base * count);

        if (entryAmountInput) entryAmountInput.value = formatCentsValue(amounts[0]);
        if (entryDueDateInput) entryDueDateInput.value = startDate;

        const installmentRowsPayload = [];
        const installmentCount = count - 1;
        for (let index = 1; index < count; index++) {
            installmentRowsPayload.push({
                label: `Parcela ${index}/${installmentCount}`,
                installment_type: 'parcela',
                installment_number: index,
                due_date: addMonthsToInputDate(startDate, index),
                amount_cents: amounts[index],
                status: 'pendente',
            });
        }

        rebuildInstallmentRows(installmentRowsPayload);
        autoSplitModal?.close();
    }

    function applySingleInstallment() {
        const agreementTotal = moneyCentsFromInput(agreementTotalInput);
        if (agreementTotal <= 0) {
            alert('Informe o valor do acordo antes de criar a parcela única.');
            agreementTotalInput?.focus();
            return;
        }

        if (entryAmountInput) entryAmountInput.value = '';
        if (entryDueDateInput) entryDueDateInput.value = '';
        if (entryStatusInput) entryStatusInput.value = '';
        updateEntryStatusCustomField();

        rebuildInstallmentRows([{
            label: 'PARCELA ÚNICA',
            installment_type: 'parcela',
            installment_number: 1,
            due_date: '',
            amount_cents: agreementTotal,
            status: 'pendente',
        }]);
    }

    function bindRemoveButtons(scope) {
        document.querySelectorAll(`[data-repeater-container="${scope}"] [data-repeater-remove]`).forEach((button) => {
            button.onclick = () => {
                const rows = document.querySelectorAll(`[data-repeater-container="${scope}"] [data-repeater-row]`);
                if (rows.length <= 1) return;
                button.closest('[data-repeater-row]')?.remove();
                reindexRepeater(scope);
                if (scope === 'installments') {
                    updatePaymentBalance();
                }
            };
        });
    }

    function initRepeater(scope) {
        const container = document.querySelector(`[data-repeater-container="${scope}"]`);
        const template = document.querySelector(`[data-repeater-template="${scope}"]`);
        const addButton = document.querySelector(`[data-repeater-add="${scope}"]`);
        if (!container || !template || !addButton) return;

        addButton.addEventListener('click', () => {
            reindexRepeater(scope);
            const index = container.querySelectorAll('[data-repeater-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', index);
            container.insertAdjacentHTML('beforeend', html);
            bindRemoveButtons(scope);
            bindMoneyMask(container);
            bindPhoneMask(container);
            bindReferencePeriodMask(container);
            bindEmailNormalization(container);
            if (scope === 'installments') {
                updatePaymentBalance();
            }
        });

        reindexRepeater(scope);
        bindRemoveButtons(scope);
    }

    condominiumSelect?.addEventListener('change', () => handleCondominiumChange('', ''));
    blockSelect?.addEventListener('change', () => {
        populateUnits(condominiumSelect?.value || '', blockSelect?.value || '', '');
        syncOwnerSummary();
    });
    unitSelect?.addEventListener('change', () => {
        syncOwnerSummary();
        seedNotificationContactsFromSelectedUnit();
    });
    chargeType?.addEventListener('change', updateChargeType);
    entryStatusInput?.addEventListener('change', updateEntryStatusCustomField);
    autoSplitButton?.addEventListener('click', openAutoSplitModal);
    autoSplitCancel?.addEventListener('click', () => autoSplitModal?.close());
    autoSplitForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        applyAutoSplit();
    });
    singleInstallmentButton?.addEventListener('click', applySingleInstallment);
    form?.addEventListener('change', (event) => {
        if (event.target?.matches?.('[name="entry_due_date"], [data-repeater-container="installments"] input, [data-repeater-container="installments"] select')) {
            updatePaymentBalance();
        }
    });
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
    updateEntryStatusCustomField();
    initRepeater('emails');
    initRepeater('phones');
    initRepeater('quotas');
    initRepeater('installments');
    bindMoneyMask(document);
    bindPhoneMask(document);
    bindReferencePeriodMask(document);
    bindEmailNormalization(document);
    updatePaymentBalance();
    syncOwnerSummary();
    if (isCreateMode && initialUnitId) {
        seedNotificationContactsFromSelectedUnit();
    }
})();
</script>
@endpush
