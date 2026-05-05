@extends('layouts.app')

@php
    $contract = $item;
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $valueOf = fn ($key, $fallback = null) => old($key, $contract?->{$key} ?? $draft[$key] ?? $fallback);
    $dateValue = function ($key) use ($valueOf) {
        $value = $valueOf($key);
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return (string) ($value ?? '');
    };
    $moneyValue = function ($key) use ($valueOf) {
        $value = $valueOf($key);
        if ($value === null || $value === '') {
            return '';
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    };
@endphp

@section('content')
<x-ancora.section-header :title="$mode === 'create' ? 'Novo contrato' : (($contract?->code ?: 'Editar contrato'))" subtitle="Cadastre contratos, termos, aditivos e demais instrumentos com preview editavel e versionamento em PDF.">
    <a href="{{ route('contratos.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
</x-ancora.section-header>

<form method="post" action="{{ $mode === 'create' ? route('contratos.store') : route('contratos.update', $contract) }}" class="space-y-6" id="contract-form" data-existing-receivables-count="{{ (int) ($existingReceivablesCount ?? 0) }}">
    @csrf
    @if($mode === 'edit')
        @method('PUT')
    @endif

    <input type="hidden" name="confirm_active_without_financial" value="0" data-contract-confirm-no-financial>
    <input type="hidden" name="financial_entries_action" value="" data-contract-financial-action>

    @if(!empty($formAlerts))
        <div class="space-y-3">
            @foreach($formAlerts as $alert)
                <div class="rounded-2xl border px-5 py-4 text-sm {{ ($alert['type'] ?? '') === 'warning' ? 'border-warning-300 bg-warning-50 text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200' : 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200' }}">
                    <div class="font-semibold">{{ $alert['label'] ?? 'Alerta' }}</div>
                    <div class="mt-1">{{ $alert['message'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <div id="contract-form-client-errors" class="hidden rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/30 dark:text-error-300">
        <div class="font-semibold">Revise os campos abaixo antes de salvar.</div>
        <ul class="mt-2 list-disc space-y-1 pl-5" data-contract-error-list></ul>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr,360px]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Etapa 1</div>
                    <h3 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Codigo interno</label>
                        <input name="code" value="{{ $valueOf('code') }}" class="{{ $inputClass }}" placeholder="Automatico ou manual">
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Titulo do contrato</label>
                        <input name="title" id="contract-title" value="{{ $valueOf('title') }}" class="{{ $inputClass }}" placeholder="Sera preenchido automaticamente pelo template, mas pode ser ajustado se necessario.">
                    </div>
                    <div data-contract-field="type">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                        <select name="type" id="contract-type" data-contract-input="type" required class="{{ $inputClass }}">
                            @foreach($typeOptions as $type)
                                <option value="{{ $type }}" @selected($valueOf('type', $typeOptions[0] ?? '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Categoria</label>
                        <select name="category_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) $valueOf('category_id') === (int) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Template</label>
                        <select name="template_id" id="contract-template-id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($templates as $template)
                                <option
                                    value="{{ $template->id }}"
                                    data-default-title="{{ $template->default_contract_title ?: $template->name }}"
                                    data-document-type="{{ $template->document_type }}"
                                    @selected((int) $valueOf('template_id') === (int) $template->id)
                                >
                                    {{ $template->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="client_id">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente vinculado</label>
                        <select name="client_id" id="contract-client-id" data-contract-input="client_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected((int) $valueOf('client_id') === (int) $client->id)>{{ $client->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="condominium_id">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Condominio vinculado</label>
                        <select name="condominium_id" id="contract-condominium-id" data-contract-input="condominium_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($condominiums as $condominium)
                                <option
                                    value="{{ $condominium->id }}"
                                    data-syndic-id="{{ $condominium->syndico_entity_id }}"
                                    @selected((int) $valueOf('condominium_id') === (int) $condominium->id)
                                >
                                    {{ $condominium->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="syndico_entity_id">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Sindico vinculado</label>
                        <select name="syndico_entity_id" id="contract-syndic-id" data-contract-input="syndico_entity_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($syndics as $syndic)
                                <option value="{{ $syndic->id }}" @selected((int) $valueOf('syndico_entity_id') === (int) $syndic->id)>
                                    {{ $syndic->display_name }}
                                    @if(($syndic->entity_type ?? '') === 'pj' && !empty($syndic->cpf_cnpj))
                                        · {{ $syndic->cpf_cnpj }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="unit_id">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Unidade vinculada</label>
                        <select name="unit_id" id="contract-unit-id" data-contract-input="unit_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" data-condominium-id="{{ $unit->condominium_id }}" data-owner-id="{{ $unit->owner_entity_id }}" @selected((int) $valueOf('unit_id') === (int) $unit->id)>
                                    {{ $unit->condominium?->name ?: 'Condominio' }}{{ $unit->block?->name ? ' · '.$unit->block->name : '' }} · Unidade {{ $unit->unit_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Proposta vinculada</label>
                        <select name="proposal_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($proposals as $proposal)
                                <option value="{{ $proposal->id }}" @selected((int) $valueOf('proposal_id') === (int) $proposal->id)>{{ $proposal->proposal_code }} · {{ $proposal->client_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Processo vinculado</label>
                        <select name="process_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($processes as $process)
                                <option value="{{ $process->id }}" @selected((int) $valueOf('process_id') === (int) $process->id)>{{ $process->process_number ?: ('Processo #' . $process->id) }} · {{ $process->client_name_snapshot }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Etapa 2</div>
                    <h3 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Dados contratuais e financeiros</h3>
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div data-contract-field="status">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" id="contract-status" data-contract-input="status" class="{{ $inputClass }}">
                            @foreach($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('status', 'rascunho') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="start_date">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de inicio</label>
                        <input type="date" name="start_date" id="contract-start-date" data-contract-input="start_date" value="{{ $dateValue('start_date') }}" class="{{ $inputClass }}">
                    </div>
                    <div data-contract-field="end_date">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de termino</label>
                        <input type="date" name="end_date" id="contract-end-date" data-contract-input="end_date" value="{{ $dateValue('end_date') }}" class="{{ $inputClass }}">
                    </div>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200" data-contract-field="indefinite_term">
                        <input type="hidden" name="indefinite_term" value="0">
                        <input type="checkbox" name="indefinite_term" id="contract-indefinite-term" data-contract-input="indefinite_term" value="1" @checked($valueOf('indefinite_term', true))>
                        Prazo indeterminado
                    </label>
                    <div data-contract-field="contract_value">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor do contrato</label>
                        <input name="contract_value" id="contract-contract-value" data-contract-input="contract_value" value="{{ $moneyValue('contract_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div data-contract-field="monthly_value">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor mensal</label>
                        <input name="monthly_value" id="contract-monthly-value" data-contract-input="monthly_value" value="{{ $moneyValue('monthly_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div data-contract-field="total_value">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor total</label>
                        <input name="total_value" id="contract-total-value" data-contract-input="total_value" value="{{ $moneyValue('total_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div data-contract-field="billing_type">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de cobranca</label>
                        <select name="billing_type" id="contract-billing-type" data-contract-input="billing_type" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($billingTypes as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('billing_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="installment_quantity">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantidade de parcelas</label>
                        <input type="number" min="1" step="1" name="installment_quantity" id="contract-installment-quantity" data-contract-input="installment_quantity" value="{{ $valueOf('installment_quantity') }}" class="{{ $inputClass }}" placeholder="Ex.: 1, 6, 12">
                    </div>
                    <div data-contract-field="financial_account_id">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Banco / conta</label>
                        <select name="financial_account_id" id="contract-financial-account-id" data-contract-input="financial_account_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($financialAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) $valueOf('financial_account_id') === (int) $account->id)>
                                    {{ $account->name }}{{ $account->bank_name ? ' · '.$account->bank_name : '' }}{{ $account->account_number ? ' · '.$account->account_number : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="payment_method">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de pagamento</label>
                        <select name="payment_method" id="contract-payment-method" data-contract-input="payment_method" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($paymentMethods as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('payment_method') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="due_day">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Dia de vencimento</label>
                        <input type="number" min="1" max="31" name="due_day" id="contract-due-day" data-contract-input="due_day" value="{{ $valueOf('due_day') }}" class="{{ $inputClass }}">
                    </div>
                    <div data-contract-field="recurrence">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Recorrencia</label>
                        <select name="recurrence" id="contract-recurrence" data-contract-input="recurrence" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($recurrenceOptions as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('recurrence') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="adjustment_index">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Indice de reajuste</label>
                        <input name="adjustment_index" id="contract-adjustment-index" data-contract-input="adjustment_index" value="{{ $valueOf('adjustment_index') }}" class="{{ $inputClass }}">
                    </div>
                    <div data-contract-field="adjustment_periodicity">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Periodicidade de reajuste</label>
                        <select name="adjustment_periodicity" id="contract-adjustment-periodicity" data-contract-input="adjustment_periodicity" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($adjustmentPeriodicities as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('adjustment_periodicity') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-contract-field="next_adjustment_date">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Proximo reajuste</label>
                        <input type="date" name="next_adjustment_date" id="contract-next-adjustment-date" data-contract-input="next_adjustment_date" value="{{ $dateValue('next_adjustment_date') }}" class="{{ $inputClass }}">
                    </div>
                    <div data-contract-field="penalty_value">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa em valor</label>
                        <input name="penalty_value" id="contract-penalty-value" data-contract-input="penalty_value" value="{{ $moneyValue('penalty_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div data-contract-field="penalty_percentage">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa em %</label>
                        <input name="penalty_percentage" id="contract-penalty-percentage" data-contract-input="penalty_percentage" value="{{ $valueOf('penalty_percentage') ? number_format((float) $valueOf('penalty_percentage'), 2, ',', '.') : '' }}" class="{{ $inputClass }}" placeholder="0,00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Responsavel</label>
                        <select name="responsible_user_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) $valueOf('responsible_user_id') === (int) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200" data-contract-field="generate_financial_entries">
                        <input type="hidden" name="generate_financial_entries" value="0">
                        <input type="checkbox" name="generate_financial_entries" id="contract-generate-financial" data-contract-input="generate_financial_entries" value="1" @checked($valueOf('generate_financial_entries'))>
                        <span>
                            <span class="block">Gerar cobrancas automaticas no Financeiro 360</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Ao ativar, o sistema cria automaticamente os lancamentos financeiros quando o contrato for salvo como assinado ou ativo.</span>
                        </span>
                    </label>
                    <div data-contract-field="cost_center_future">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Centro de custo futuro</label>
                        <input name="cost_center_future" id="contract-cost-center-future" data-contract-input="cost_center_future" value="{{ $valueOf('cost_center_future') }}" class="{{ $inputClass }}">
                    </div>
                    <div data-contract-field="financial_category_future">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Categoria financeira futura</label>
                        <input name="financial_category_future" id="contract-financial-category-future" data-contract-input="financial_category_future" value="{{ $valueOf('financial_category_future') }}" class="{{ $inputClass }}">
                    </div>
                    <div class="md:col-span-2 xl:col-span-3" data-contract-field="notes">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacoes internas</label>
                        <textarea name="notes" id="contract-notes" data-contract-input="notes" rows="4" class="{{ $textareaClass }}">{{ $valueOf('notes') }}</textarea>
                    </div>
                    <div class="md:col-span-2 xl:col-span-3" data-contract-field="financial_notes">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacoes financeiras</label>
                        <textarea name="financial_notes" id="contract-financial-notes" data-contract-input="financial_notes" rows="4" class="{{ $textareaClass }}">{{ $valueOf('financial_notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Etapa 3</div>
                        <h3 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Preview editavel do contrato</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Escolha o template, carregue as variaveis e ajuste o texto antes de salvar ou gerar a versao final.</p>
                    </div>
                    <button type="button" id="load-contract-preview" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Carregar / atualizar preview</button>
                </div>
                <div class="mt-5">
                    @include('pages.contratos.partials.rich-editor', [
                        'editorId' => 'contract-content-editor',
                        'name' => 'content_html',
                        'value' => $previewHtml,
                        'placeholder' => 'Carregue o template e ajuste as clausulas aqui.',
                        'minHeight' => '420px',
                        'variableDefinitions' => $variableDefinitions,
                        'showVariablePicker' => true,
                    ])
                </div>
                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacao da versao</label>
                    <input name="version_notes" value="{{ old('version_notes') }}" class="{{ $inputClass }}" placeholder="Ex.: versao inicial, ajuste de clausula quinta, atualizacao de valores...">
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="text-base font-semibold text-gray-900 dark:text-white">Etapa 4 - Finalizacao</div>
                <div class="mt-4 space-y-4 text-sm text-gray-600 dark:text-gray-300">
                    <p>Salve em rascunho, ajuste o preview e gere o PDF final somente quando o texto estiver validado.</p>
                    <p>O PDF gerado cria automaticamente uma nova versao no historico do contrato.</p>
                    <p>Se o template estiver vazio, o sistema usa o conteudo atualmente editado para atualizar as variaveis informadas.</p>
                </div>
            </div>

            @if($item?->final_pdf_path)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-base font-semibold text-gray-900 dark:text-white">PDF atual</div>
                    <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">Ultimo PDF gerado em {{ optional($item->final_pdf_generated_at)->format('d/m/Y H:i') ?: 'data nao informada' }}.</div>
                    <a href="{{ route('contratos.download-pdf', $item) }}" class="mt-4 inline-flex rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Baixar PDF final</a>
                </div>
            @endif
        </aside>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ route('contratos.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar contrato</button>
        @if($mode === 'edit' && $item)
            <button type="button" id="open-contract-pdf-modal" class="rounded-xl border border-success-300 bg-success-50 px-5 py-3 text-sm font-medium text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-200">Salvar e gerar PDF</button>
        @else
            <button type="submit" name="generate_pdf_now" value="1" class="rounded-xl border border-success-300 bg-success-50 px-5 py-3 text-sm font-medium text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-200">Salvar e gerar PDF</button>
        @endif
    </div>

    @if($mode === 'edit' && $item)
        <dialog id="contract-form-pdf-modal" class="fixed inset-0 m-auto w-full max-w-2xl rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Salvar e gerar PDF</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Selecione os documentos do cadastro que devem entrar como anexo no final do contrato.</p>
                    </div>
                    <button type="button" id="close-contract-pdf-modal" class="rounded-full border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
                </div>

                <div class="mt-5 rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Documentos disponiveis</div>
                    <div class="mt-3 space-y-3">
                        @forelse($pdfAppendixAttachments as $attachment)
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <input type="checkbox" name="pdf_attachment_ids[]" value="{{ $attachment['id'] }}">
                                <span>
                                    <span class="block font-semibold text-gray-900 dark:text-white">{{ $attachment['original_name'] }}</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $attachment['owner_label'] }} · {{ strtoupper($attachment['extension']) }} · {{ ucfirst(str_replace('_', ' ', $attachment['file_role'])) }}</span>
                                </span>
                            </label>
                        @empty
                            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Nenhum documento elegivel foi encontrado nos cadastros vinculados. O PDF sera gerado somente com o conteudo do contrato.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" id="cancel-contract-pdf-modal" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
                    <button type="submit" name="generate_pdf_now" value="1" class="rounded-xl border border-success-300 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-200">Salvar e gerar PDF</button>
                </div>
            </div>
        </dialog>
    @endif

    <dialog id="contract-financial-confirm-modal" class="fixed inset-0 m-auto w-full max-w-xl rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" data-contract-confirm-title>Confirmacao financeira</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" data-contract-confirm-message></p>
                </div>
                <button type="button" id="close-contract-financial-confirm-modal" class="rounded-full border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button type="button" id="cancel-contract-financial-confirm-modal" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
                <button type="button" id="secondary-contract-financial-confirm-modal" class="hidden rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200"></button>
                <button type="button" id="confirm-contract-financial-confirm-modal" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white"></button>
            </div>
        </div>
    </dialog>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#contract-form');
    if (!form) {
        return;
    }

    const previewButton = document.querySelector('#load-contract-preview');
    const editor = document.querySelector('[data-rich-editor="contract-content-editor"]');
    const editorInput = document.querySelector('[data-rich-editor-input="contract-content-editor"]');
    const templateSelect = document.querySelector('#contract-template-id');
    const titleInput = document.querySelector('#contract-title');
    const typeSelect = document.querySelector('#contract-type');
    const clientSelect = document.querySelector('#contract-client-id');
    const condominiumSelect = document.querySelector('#contract-condominium-id');
    const syndicSelect = document.querySelector('#contract-syndic-id');
    const unitSelect = document.querySelector('#contract-unit-id');
    const statusSelect = document.querySelector('#contract-status');
    const startDateInput = document.querySelector('#contract-start-date');
    const endDateInput = document.querySelector('#contract-end-date');
    const indefiniteCheckbox = document.querySelector('#contract-indefinite-term');
    const billingTypeSelect = document.querySelector('#contract-billing-type');
    const installmentInput = document.querySelector('#contract-installment-quantity');
    const totalValueInput = document.querySelector('#contract-total-value');
    const contractValueInput = document.querySelector('#contract-contract-value');
    const monthlyValueInput = document.querySelector('#contract-monthly-value');
    const financialAccountSelect = document.querySelector('#contract-financial-account-id');
    const paymentMethodSelect = document.querySelector('#contract-payment-method');
    const dueDayInput = document.querySelector('#contract-due-day');
    const recurrenceSelect = document.querySelector('#contract-recurrence');
    const adjustmentIndexInput = document.querySelector('#contract-adjustment-index');
    const adjustmentPeriodicitySelect = document.querySelector('#contract-adjustment-periodicity');
    const nextAdjustmentDateInput = document.querySelector('#contract-next-adjustment-date');
    const penaltyValueInput = document.querySelector('#contract-penalty-value');
    const penaltyPercentageInput = document.querySelector('#contract-penalty-percentage');
    const generateFinancialCheckbox = document.querySelector('#contract-generate-financial');
    const financialNotesInput = document.querySelector('#contract-financial-notes');
    const notesInput = document.querySelector('#contract-notes');
    const costCenterFutureInput = document.querySelector('#contract-cost-center-future');
    const financialCategoryFutureInput = document.querySelector('#contract-financial-category-future');
    const errorBox = document.querySelector('#contract-form-client-errors');
    const errorList = errorBox?.querySelector('[data-contract-error-list]');
    const confirmNoFinancialInput = form.querySelector('[data-contract-confirm-no-financial]');
    const financialActionInput = form.querySelector('[data-contract-financial-action]');
    const pdfModal = document.querySelector('#contract-form-pdf-modal');
    const openPdfModalButton = document.querySelector('#open-contract-pdf-modal');
    const closePdfModalButton = document.querySelector('#close-contract-pdf-modal');
    const cancelPdfModalButton = document.querySelector('#cancel-contract-pdf-modal');
    const financialModal = document.querySelector('#contract-financial-confirm-modal');
    const financialModalTitle = financialModal?.querySelector('[data-contract-confirm-title]');
    const financialModalMessage = financialModal?.querySelector('[data-contract-confirm-message]');
    const financialModalConfirm = document.querySelector('#confirm-contract-financial-confirm-modal');
    const financialModalSecondary = document.querySelector('#secondary-contract-financial-confirm-modal');
    const financialModalCancel = document.querySelector('#cancel-contract-financial-confirm-modal');
    const financialModalClose = document.querySelector('#close-contract-financial-confirm-modal');
    const fieldWrappers = new Map(Array.from(form.querySelectorAll('[data-contract-field]')).map((element) => [element.dataset.contractField, element]));
    const fieldInputs = new Map(Array.from(form.querySelectorAll('[data-contract-input]')).map((element) => [element.dataset.contractInput, element]));
    const moneyFields = Array.from(form.querySelectorAll('[data-money]'));
    const unitOptions = unitSelect ? Array.from(unitSelect.options).slice(1) : [];

    const labelMap = {
        type: 'Tipo',
        client_id: 'Cliente vinculado',
        condominium_id: 'Condominio vinculado',
        syndico_entity_id: 'Sindico vinculado',
        unit_id: 'Unidade vinculada',
        status: 'Status',
        start_date: 'Data de inicio',
        end_date: 'Data de termino',
        contract_value: 'Valor do contrato',
        monthly_value: 'Valor mensal',
        total_value: 'Valor total',
        billing_type: 'Forma de cobranca',
        installment_quantity: 'Quantidade de parcelas',
        financial_account_id: 'Banco / conta',
        payment_method: 'Forma de pagamento',
        due_day: 'Dia de vencimento',
        recurrence: 'Recorrencia',
        adjustment_index: 'Indice de reajuste',
        adjustment_periodicity: 'Periodicidade de reajuste',
        next_adjustment_date: 'Proximo reajuste',
        penalty_value: 'Multa em valor',
        penalty_percentage: 'Multa em percentual',
        financial_notes: 'Observacoes financeiras',
        notes: 'Observacoes internas',
        cost_center_future: 'Centro de custo futuro',
        financial_category_future: 'Categoria financeira futura',
        generate_financial_entries: 'Gerar cobrancas automaticas no Financeiro 360',
    };

    const contractTypes = {
        assessoria: normalize('Contrato de assessoria juridica condominial'),
        termo: normalize('Termo de acordo'),
        confissao: normalize('Confissao de divida'),
        distrato: normalize('Distrato'),
    };
    const initialCondominiumSyndicId = condominiumSelect?.selectedOptions[0]?.dataset.syndicId || '';
    const initialState = {
        status: normalize(statusSelect?.value),
        generateFinancialEntries: Boolean(generateFinancialCheckbox?.checked),
        existingReceivablesCount: Number.parseInt(form.dataset.existingReceivablesCount || '0', 10) || 0,
    };

    fieldInputs.set('indefinite_term', indefiniteCheckbox);
    fieldInputs.set('generate_financial_entries', generateFinancialCheckbox);

    let lastAutoTitle = titleInput ? titleInput.value.trim() : '';
    let syndicTouched = Boolean(syndicSelect && syndicSelect.value && String(syndicSelect.value) !== String(initialCondominiumSyndicId || ''));
    let submitGuardBypassed = false;
    let pendingSubmitter = null;
    let financialModalActions = {
        confirm: null,
        secondary: null,
        cancel: null,
    };

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function normalizeEditorHtml(html) {
        const value = String(html || '').trim();
        if (!value) {
            return '';
        }

        const container = document.createElement('div');
        container.innerHTML = value;
        const plain = (container.textContent || container.innerText || '')
            .replace(/\u00a0/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        return plain === '' ? '' : value;
    }

    function formatMoneyField(field) {
        const digits = String(field.value || '').replace(/\D/g, '');
        if (!digits) {
            field.value = '';
            return;
        }

        const amount = Number(digits) / 100;
        field.value = `R$ ${amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function parseDecimal(value) {
        const normalizedValue = String(value || '')
            .replace(/\s/g, '')
            .replace(/[R$r$\u00a0]/g, '')
            .replace(/\./g, '')
            .replace(',', '.')
            .replace(/[^0-9.-]/g, '');
        const number = Number(normalizedValue);

        return Number.isFinite(number) ? number : 0;
    }

    function hasPositiveValue(element, isMoney = false) {
        if (!element) {
            return false;
        }

        return isMoney ? parseDecimal(element.value) > 0 : parseDecimal(element.value) > 0;
    }

    function hasFieldValue(name) {
        const control = fieldInputs.get(name);
        if (!control) {
            return false;
        }

        if (control.type === 'checkbox') {
            return control.checked;
        }

        return String(control.value || '').trim() !== '';
    }

    function clearField(name) {
        const control = fieldInputs.get(name);
        if (!control) {
            return;
        }

        if (control.type === 'checkbox') {
            control.checked = false;
            return;
        }

        control.value = '';
    }

    function ensureEditorInput() {
        if (editor && editorInput) {
            editorInput.value = normalizeEditorHtml(editor.innerHTML);
        }
    }

    function closeDialog(dialog) {
        if (!dialog) {
            return;
        }

        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        }
    }

    function openDialog(dialog) {
        if (!dialog) {
            return false;
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            return true;
        }

        return false;
    }

    function syncTemplateDefaults() {
        if (!templateSelect) {
            return false;
        }

        const option = templateSelect.selectedOptions[0];
        if (!option) {
            return false;
        }

        let typeChanged = false;
        const nextTitle = (option.dataset.defaultTitle || '').trim();
        const nextType = (option.dataset.documentType || '').trim();

        if (titleInput && nextTitle !== '' && (titleInput.value.trim() === '' || titleInput.value.trim() === lastAutoTitle)) {
            titleInput.value = nextTitle;
            lastAutoTitle = nextTitle;
        }

        if (typeSelect && nextType !== '' && typeSelect.value !== nextType) {
            typeSelect.value = nextType;
            typeChanged = true;
        }

        return typeChanged;
    }

    function syncSyndicFromCondominium(force = false) {
        if (!condominiumSelect || !syndicSelect || (syndicTouched && !force)) {
            return;
        }

        const option = condominiumSelect.selectedOptions[0];
        if (!option) {
            return;
        }

        const syndicId = option.dataset.syndicId || '';
        if (syndicId !== '') {
            syndicSelect.value = syndicId;
        }
    }

    function filterUnitsForCondominium(context = { isInit: false }) {
        if (!unitSelect) {
            return;
        }

        const condominiumId = condominiumSelect?.value || '';
        unitOptions.forEach((option) => {
            const matches = condominiumId === '' || String(option.dataset.condominiumId || '') === String(condominiumId);
            option.hidden = !matches;
        });

        const selectedOption = unitSelect.selectedOptions[0];
        if (!selectedOption || !selectedOption.value) {
            return;
        }

        if (selectedOption.hidden && !context.isInit) {
            unitSelect.value = '';
        }
    }

    function syncLinkedEntitiesFromUnit(context = { isInit: false }) {
        if (!unitSelect) {
            return;
        }

        const option = unitSelect.selectedOptions[0];
        if (!option || !option.value) {
            return;
        }

        const condominiumId = option.dataset.condominiumId || '';
        const ownerId = option.dataset.ownerId || '';

        if (condominiumSelect && condominiumId !== '' && condominiumSelect.value !== condominiumId) {
            condominiumSelect.value = condominiumId;
            syncSyndicFromCondominium(context.isInit);
            filterUnitsForCondominium(context);
        }

        if (clientSelect && ownerId !== '' && clientSelect.value === '') {
            clientSelect.value = ownerId;
        }
    }

    function readState() {
        return {
            type: normalize(typeSelect?.value),
            status: normalize(statusSelect?.value),
            billingType: normalize(billingTypeSelect?.value),
            paymentMethod: normalize(paymentMethodSelect?.value),
            indefiniteTerm: Boolean(indefiniteCheckbox?.checked),
            generateFinancialEntries: Boolean(generateFinancialCheckbox?.checked),
            endDate: String(endDateInput?.value || '').trim(),
            adjustmentIndex: String(adjustmentIndexInput?.value || '').trim(),
            paymentDispensesAccount: ['especie', 'dinheiro'].includes(normalize(paymentMethodSelect?.value)),
            isMonthly: normalize(billingTypeSelect?.value) === 'mensal',
            isParcelado: normalize(billingTypeSelect?.value) === 'parcelada',
            isParcelaUnica: normalize(billingTypeSelect?.value) === 'unica',
            isActiveOrSigned: ['ativo', 'assinado'].includes(normalize(statusSelect?.value)),
            isTerminalStatus: ['rescindido', 'cancelado', 'arquivado'].includes(normalize(statusSelect?.value)),
            existingReceivablesCount: initialState.existingReceivablesCount,
        };
    }

    function buildConfig() {
        const config = {};
        fieldWrappers.forEach((wrapper, name) => {
            config[name] = {
                enabled: true,
                required: false,
                clearOnDisable: false,
            };
        });

        return config;
    }

    function enableField(config, name) {
        if (!config[name]) {
            return;
        }

        config[name].enabled = true;
    }

    function disableField(config, name, options = {}) {
        if (!config[name]) {
            return;
        }

        config[name].enabled = false;
        if (options.clear) {
            config[name].clearOnDisable = true;
        }
    }

    function requireField(config, name) {
        if (!config[name]) {
            return;
        }

        config[name].required = true;
    }

    function applyAutomaticValues(context) {
        let state = readState();

        if (state.type === contractTypes.assessoria) {
            if (billingTypeSelect && billingTypeSelect.value === '') {
                billingTypeSelect.value = 'mensal';
            }

            if (recurrenceSelect && recurrenceSelect.value === '') {
                recurrenceSelect.value = 'mensal';
            }
        }

        state = readState();

        if (state.isParcelaUnica && installmentInput) {
            installmentInput.value = '1';
        }

        if (state.isParcelado && installmentInput && !context.isInit) {
            const currentInstallments = Number.parseInt(installmentInput.value || '0', 10) || 0;
            if (context.source === 'billing_type' && currentInstallments < 2) {
                installmentInput.value = '2';
            }
        }

        state = readState();

        if (
            state.isTerminalStatus ||
            state.type === contractTypes.distrato ||
            ['honorarios_sobre_exito', 'sob_demanda'].includes(state.billingType) ||
            (state.paymentMethod === 'cheque' && state.isMonthly)
        ) {
            if (generateFinancialCheckbox) {
                generateFinancialCheckbox.checked = false;
            }
        }

        if (state.type === contractTypes.distrato && indefiniteCheckbox && !context.isInit && context.source === 'type') {
            indefiniteCheckbox.checked = false;
        }

        return readState();
    }

    function toggleContractFields(state, config, context) {
        const clearForExplicitToggle = !context.isInit && ['indefinite_term', 'type', 'status'].includes(context.source);

        if (state.indefiniteTerm) {
            disableField(config, 'end_date', { clear: !context.isInit && context.source === 'indefinite_term' });
        } else {
            enableField(config, 'end_date');
        }

        if (state.isTerminalStatus) {
            disableField(config, 'generate_financial_entries');
        }

        if (state.type === contractTypes.termo || state.type === contractTypes.confissao) {
            enableField(config, 'client_id');
            enableField(config, 'condominium_id');
            enableField(config, 'unit_id');
            enableField(config, 'total_value');
            enableField(config, 'billing_type');
            enableField(config, 'installment_quantity');
            enableField(config, 'due_day');
            enableField(config, 'penalty_value');
            enableField(config, 'penalty_percentage');
            disableField(config, 'monthly_value', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_index', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_periodicity', { clear: clearForExplicitToggle });
            disableField(config, 'next_adjustment_date', { clear: clearForExplicitToggle });
        }

        if (state.type === contractTypes.assessoria) {
            enableField(config, 'condominium_id');
            enableField(config, 'syndico_entity_id');
            enableField(config, 'monthly_value');
            enableField(config, 'due_day');
            enableField(config, 'recurrence');
            enableField(config, 'adjustment_index');
            enableField(config, 'adjustment_periodicity');
            enableField(config, 'next_adjustment_date');
            enableField(config, 'generate_financial_entries');
            disableField(config, 'unit_id');
        }

        if (state.type === contractTypes.distrato) {
            enableField(config, 'client_id');
            enableField(config, 'condominium_id');
            enableField(config, 'end_date');
            enableField(config, 'notes');
            enableField(config, 'financial_notes');
            disableField(config, 'generate_financial_entries');
            disableField(config, 'recurrence', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_index', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_periodicity', { clear: clearForExplicitToggle });
            disableField(config, 'next_adjustment_date', { clear: clearForExplicitToggle });
            disableField(config, 'installment_quantity');
        }
    }

    function toggleBillingFields(state, config, context) {
        const clearForExplicitToggle = !context.isInit && ['billing_type', 'type'].includes(context.source);

        if (state.isParcelaUnica) {
            enableField(config, 'total_value');
            enableField(config, 'payment_method');
            enableField(config, 'financial_account_id');
            enableField(config, 'start_date');
            enableField(config, 'due_day');
            disableField(config, 'monthly_value', { clear: clearForExplicitToggle });
            disableField(config, 'recurrence', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_index', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_periodicity', { clear: clearForExplicitToggle });
            disableField(config, 'next_adjustment_date', { clear: clearForExplicitToggle });
            disableField(config, 'installment_quantity');
        }

        if (state.isParcelado) {
            enableField(config, 'total_value');
            enableField(config, 'installment_quantity');
            enableField(config, 'due_day');
            enableField(config, 'payment_method');
            enableField(config, 'financial_account_id');
            enableField(config, 'generate_financial_entries');
            disableField(config, 'monthly_value', { clear: clearForExplicitToggle });
            disableField(config, 'recurrence', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_index', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_periodicity', { clear: clearForExplicitToggle });
            disableField(config, 'next_adjustment_date', { clear: clearForExplicitToggle });
        }

        if (state.isMonthly) {
            enableField(config, 'monthly_value');
            enableField(config, 'due_day');
            enableField(config, 'recurrence');
            enableField(config, 'adjustment_index');
            enableField(config, 'adjustment_periodicity');
            enableField(config, 'next_adjustment_date');
            enableField(config, 'generate_financial_entries');
            disableField(config, 'installment_quantity', { clear: clearForExplicitToggle });

            if (state.indefiniteTerm) {
                disableField(config, 'total_value', { clear: !context.isInit && context.source === 'billing_type' });
            } else {
                enableField(config, 'total_value');
            }
        }

        if (state.billingType === 'honorarios_sobre_exito') {
            enableField(config, 'contract_value');
            enableField(config, 'total_value');
            enableField(config, 'financial_notes');
            disableField(config, 'monthly_value', { clear: clearForExplicitToggle });
            disableField(config, 'due_day', { clear: clearForExplicitToggle });
            disableField(config, 'recurrence', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_index', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_periodicity', { clear: clearForExplicitToggle });
            disableField(config, 'next_adjustment_date', { clear: clearForExplicitToggle });
            disableField(config, 'installment_quantity', { clear: clearForExplicitToggle });
            disableField(config, 'generate_financial_entries');
        }

        if (state.billingType === 'sob_demanda') {
            enableField(config, 'financial_notes');
            enableField(config, 'cost_center_future');
            enableField(config, 'financial_category_future');
            disableField(config, 'monthly_value', { clear: clearForExplicitToggle });
            disableField(config, 'total_value');
            disableField(config, 'due_day', { clear: clearForExplicitToggle });
            disableField(config, 'recurrence', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_index', { clear: clearForExplicitToggle });
            disableField(config, 'adjustment_periodicity', { clear: clearForExplicitToggle });
            disableField(config, 'next_adjustment_date', { clear: clearForExplicitToggle });
            disableField(config, 'installment_quantity', { clear: clearForExplicitToggle });
            disableField(config, 'generate_financial_entries');
        }
    }

    function togglePaymentFields(state, config, context) {
        const clearForExplicitToggle = !context.isInit && context.source === 'payment_method';

        if (state.paymentDispensesAccount) {
            disableField(config, 'financial_account_id', { clear: clearForExplicitToggle });
            enableField(config, 'financial_notes');
        }

        if (state.paymentMethod === 'pix') {
            enableField(config, 'financial_account_id');
        }

        if (state.paymentMethod === 'boleto') {
            enableField(config, 'financial_account_id');
            enableField(config, 'due_day');
            enableField(config, 'generate_financial_entries');
        }

        if (['transferencia', 'deposito', 'debito_automatico'].includes(state.paymentMethod)) {
            enableField(config, 'financial_account_id');
        }

        if (state.paymentMethod === 'cartao') {
            enableField(config, 'financial_account_id');
            if (state.isParcelado) {
                enableField(config, 'installment_quantity');
            }
        }

        if (state.paymentMethod === 'cheque') {
            if (state.isMonthly && generateFinancialCheckbox) {
                generateFinancialCheckbox.checked = false;
                state.generateFinancialEntries = false;
            }
        }
    }

    function toggleAdjustmentFields(state, config) {
        if (!state.isMonthly) {
            disableField(config, 'adjustment_index');
            disableField(config, 'adjustment_periodicity');
            disableField(config, 'next_adjustment_date');
        }

        if (state.adjustmentIndex !== '') {
            requireField(config, 'adjustment_periodicity');
        }
    }

    function togglePenaltyFields(config, context) {
        const hasPenaltyValue = hasPositiveValue(penaltyValueInput, true);
        const hasPenaltyPercentage = hasPositiveValue(penaltyPercentageInput, false);

        if (context.isInit && hasPenaltyValue && hasPenaltyPercentage) {
            enableField(config, 'penalty_value');
            enableField(config, 'penalty_percentage');
            return;
        }

        if (hasPenaltyValue) {
            disableField(config, 'penalty_percentage', { clear: !context.isInit && context.source === 'penalty_value' });
        } else {
            enableField(config, 'penalty_percentage');
        }

        if (hasPenaltyPercentage) {
            disableField(config, 'penalty_value', { clear: !context.isInit && context.source === 'penalty_percentage' });
        } else {
            enableField(config, 'penalty_value');
        }
    }

    function toggleRequiredFields(state, config) {
        requireField(config, 'type');
        requireField(config, 'status');

        if (state.isParcelado) {
            requireField(config, 'installment_quantity');
        }

        if (state.paymentMethod === 'cartao' && state.isParcelado) {
            requireField(config, 'installment_quantity');
        }

        if (state.paymentMethod === 'boleto') {
            requireField(config, 'due_day');

            if (state.isMonthly) {
                requireField(config, 'recurrence');
            }
        }

        if (state.adjustmentIndex !== '') {
            requireField(config, 'adjustment_periodicity');
        }

        if (state.generateFinancialEntries && state.isActiveOrSigned) {
            requireField(config, 'billing_type');
            requireField(config, 'payment_method');
            requireField(config, 'due_day');

            if (state.isMonthly) {
                requireField(config, 'monthly_value');
                requireField(config, 'recurrence');
            }

            if (state.isParcelaUnica || state.isParcelado) {
                requireField(config, 'total_value');
            }

            if (state.isParcelado) {
                requireField(config, 'installment_quantity');
            }

            if (!state.paymentDispensesAccount) {
                requireField(config, 'financial_account_id');
            }
        }
    }

    function enforceFieldLocks(state, config) {
        if (state.type === contractTypes.assessoria) {
            disableField(config, 'unit_id');
        }

        if (state.type === contractTypes.distrato) {
            disableField(config, 'generate_financial_entries');
        }

        if (state.isTerminalStatus) {
            disableField(config, 'generate_financial_entries');
        }

        if (['honorarios_sobre_exito', 'sob_demanda'].includes(state.billingType)) {
            disableField(config, 'generate_financial_entries');
        }

        if (state.paymentMethod === 'cheque' && state.isMonthly) {
            disableField(config, 'generate_financial_entries');
        }
    }

    function applyFieldConfiguration(config, context) {
        fieldWrappers.forEach((wrapper, name) => {
            const control = fieldInputs.get(name);
            const settings = config[name] || { enabled: true, required: false, clearOnDisable: false };
            const shouldDisable = settings.enabled === false;

            if (!control) {
                return;
            }

            if (name === 'end_date') {
                if (shouldDisable && context.isInit && indefiniteCheckbox?.checked && control.value && !control.dataset.preservedDisabledValue) {
                    control.dataset.preservedDisabledValue = control.value;
                    control.dataset.visualCleared = '1';
                    control.value = '';
                }

                if (shouldDisable && settings.clearOnDisable) {
                    control.dataset.preservedDisabledValue = '';
                    control.dataset.visualCleared = '0';
                    control.value = '';
                }

                if (!shouldDisable && control.dataset.visualCleared === '1' && control.dataset.preservedDisabledValue) {
                    control.value = control.dataset.preservedDisabledValue;
                    control.dataset.visualCleared = '0';
                }
            } else if (shouldDisable && settings.clearOnDisable) {
                clearField(name);
            }

            control.disabled = shouldDisable;
            control.required = !shouldDisable && settings.required === true;

            if (shouldDisable) {
                control.dataset.contractDisabled = '1';
            } else {
                delete control.dataset.contractDisabled;
            }

            wrapper.classList.toggle('opacity-60', shouldDisable);
            wrapper.classList.toggle('pointer-events-none', shouldDisable);
            control.classList.toggle('bg-gray-100', shouldDisable);
            control.classList.toggle('cursor-not-allowed', shouldDisable);
            control.classList.toggle('dark:bg-gray-800', shouldDisable);
        });
    }

    function buildClientValidationErrors(state, config) {
        const messages = new Set();
        const supportedAutomaticBillingTypes = ['mensal', 'unica', 'parcelada'];

        Object.entries(config).forEach(([name, settings]) => {
            if (!settings.required || settings.enabled === false) {
                return;
            }

            if (!hasFieldValue(name)) {
                messages.add(`Preencha o campo ${labelMap[name] || name}.`);
            }
        });

        if (hasPositiveValue(penaltyValueInput, true) && hasPositiveValue(penaltyPercentageInput, false)) {
            messages.add('Informe a multa em valor ou em percentual, nunca as duas ao mesmo tempo.');
        }

        if (state.isParcelado) {
            const installments = Number.parseInt(installmentInput?.value || '0', 10) || 0;
            if (installments < 2) {
                messages.add('A quantidade de parcelas deve ser de no minimo 2 quando a forma de cobranca for Parcelado.');
            }
        }

        if (state.paymentMethod === 'cartao' && state.isParcelado) {
            const installments = Number.parseInt(installmentInput?.value || '0', 10) || 0;
            if (installments < 2) {
                messages.add('Pagamentos em cartao parcelado exigem a quantidade de parcelas com valor minimo de 2.');
            }
        }

        if (state.paymentMethod === 'boleto' && !hasFieldValue('due_day')) {
            messages.add('Ao selecionar Boleto, o dia de vencimento passa a ser obrigatorio.');
        }

        if (state.paymentMethod === 'boleto' && state.isMonthly && !hasFieldValue('recurrence')) {
            messages.add('Contratos mensais com pagamento em boleto exigem recorrencia definida.');
        }

        if (state.adjustmentIndex !== '' && !hasFieldValue('adjustment_periodicity')) {
            messages.add('Ao informar o indice de reajuste, a periodicidade de reajuste passa a ser obrigatoria.');
        }

        if (state.paymentMethod === 'cheque' && state.isMonthly && state.generateFinancialEntries) {
            messages.add('No fluxo atual, contratos mensais pagos por cheque nao podem gerar cobrancas automaticas recorrentes.');
        }

        if (state.generateFinancialEntries && state.isActiveOrSigned && !supportedAutomaticBillingTypes.includes(state.billingType)) {
            messages.add('A geracao automatica no Financeiro 360 esta disponivel apenas para cobranca Mensal, Parcela unica ou Parcelado.');
        }

        return Array.from(messages);
    }

    function showClientValidationErrors(messages) {
        if (!errorBox || !errorList) {
            return;
        }

        if (!messages.length) {
            errorBox.classList.add('hidden');
            errorList.innerHTML = '';
            return;
        }

        errorList.innerHTML = messages.map((message) => `<li>${message}</li>`).join('');
        errorBox.classList.remove('hidden');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function restoreDisabledValuesBeforeSubmit() {
        if (endDateInput?.dataset.visualCleared === '1' && endDateInput.dataset.preservedDisabledValue) {
            endDateInput.value = endDateInput.dataset.preservedDisabledValue;
        }
    }

    function enableDisabledFieldsBeforeSubmit() {
        restoreDisabledValuesBeforeSubmit();

        form.querySelectorAll('[data-contract-disabled="1"]').forEach((element) => {
            element.disabled = false;
        });
    }

    function closeFinancialConfirmModal(resetSubmitter = true) {
        closeDialog(financialModal);
        financialModalActions = {
            confirm: null,
            secondary: null,
            cancel: null,
        };

        if (resetSubmitter) {
            pendingSubmitter = null;
        }
    }

    function openFinancialConfirmModal(options) {
        if (!financialModal || typeof financialModal.showModal !== 'function') {
            const fallbackConfirmed = window.confirm(options.message || 'Deseja continuar?');
            if (fallbackConfirmed) {
                options.onConfirm?.();
            } else {
                options.onCancel?.();
            }
            return;
        }

        financialModalTitle.textContent = options.title || 'Confirmacao financeira';
        financialModalMessage.textContent = options.message || '';
        financialModalConfirm.textContent = options.confirmLabel || 'Confirmar';
        financialModalSecondary.textContent = options.secondaryLabel || '';
        financialModalSecondary.classList.toggle('hidden', !options.secondaryLabel);

        financialModalActions = {
            confirm: options.onConfirm || null,
            secondary: options.onSecondary || null,
            cancel: options.onCancel || null,
        };

        openDialog(financialModal);
    }

    function refreshContractFields(context = { isInit: false, source: 'init' }) {
        const state = applyAutomaticValues(context);
        const config = buildConfig();

        toggleContractFields(state, config, context);
        toggleBillingFields(state, config, context);
        togglePaymentFields(state, config, context);
        toggleAdjustmentFields(state, config);
        togglePenaltyFields(config, context);
        toggleRequiredFields(state, config);
        enforceFieldLocks(state, config);
        applyFieldConfiguration(config, context);

        if (context.isInit) {
            showClientValidationErrors([]);
        }

        return { state, config };
    }

    function shouldAskActiveWithoutFinancialConfirmation(state) {
        return state.isActiveOrSigned && !state.generateFinancialEntries;
    }

    function shouldAskExistingEntriesDecision(state) {
        return !initialState.generateFinancialEntries
            && state.generateFinancialEntries
            && state.isActiveOrSigned
            && state.existingReceivablesCount > 0;
    }

    function resumeSubmit() {
        const submitter = pendingSubmitter || undefined;
        submitGuardBypassed = true;
        pendingSubmitter = null;
        enableDisabledFieldsBeforeSubmit();
        form.requestSubmit(submitter);
    }

    function handleSubmit(event) {
        if (submitGuardBypassed) {
            ensureEditorInput();
            enableDisabledFieldsBeforeSubmit();
            return;
        }

        ensureEditorInput();
        confirmNoFinancialInput.value = '0';
        financialActionInput.value = '';

        const result = refreshContractFields({ isInit: false, source: 'submit' });
        const messages = buildClientValidationErrors(result.state, result.config);
        showClientValidationErrors(messages);

        if (messages.length) {
            event.preventDefault();
            return;
        }

        if (shouldAskExistingEntriesDecision(result.state)) {
            event.preventDefault();
            pendingSubmitter = event.submitter || null;
            openFinancialConfirmModal({
                title: 'Lancamentos financeiros ja existentes',
                message: 'Ja existem lancamentos financeiros vinculados a este contrato. Deseja manter os registros atuais ou recriar a grade financeira agora? A recriacao so sera permitida se ainda nao houver baixa ou movimentacao vinculada.',
                confirmLabel: 'Manter lancamentos',
                secondaryLabel: 'Recriar lancamentos',
                onConfirm: () => {
                    financialActionInput.value = 'maintain';
                    closeFinancialConfirmModal(false);
                    resumeSubmit();
                },
                onSecondary: () => {
                    financialActionInput.value = 'recreate';
                    closeFinancialConfirmModal(false);
                    resumeSubmit();
                },
                onCancel: closeFinancialConfirmModal,
            });
            return;
        }

        if (shouldAskActiveWithoutFinancialConfirmation(result.state)) {
            event.preventDefault();
            pendingSubmitter = event.submitter || null;
            openFinancialConfirmModal({
                title: 'Salvar sem gerar financeiro',
                message: 'ATENCAO: Este contrato sera salvo como ativo/assinado, porem NAO sera gerado lancamento automatico no Financeiro 360. Deseja continuar mesmo assim?',
                confirmLabel: 'Salvar sem gerar',
                onConfirm: () => {
                    confirmNoFinancialInput.value = '1';
                    closeFinancialConfirmModal(false);
                    resumeSubmit();
                },
                onCancel: closeFinancialConfirmModal,
            });
            return;
        }

        enableDisabledFieldsBeforeSubmit();
    }

    openPdfModalButton?.addEventListener('click', () => {
        openDialog(pdfModal);
    });

    closePdfModalButton?.addEventListener('click', () => {
        closeDialog(pdfModal);
    });

    cancelPdfModalButton?.addEventListener('click', () => {
        closeDialog(pdfModal);
    });

    financialModalConfirm?.addEventListener('click', () => {
        financialModalActions.confirm?.();
    });

    financialModalSecondary?.addEventListener('click', () => {
        financialModalActions.secondary?.();
    });

    financialModalCancel?.addEventListener('click', () => {
        financialModalActions.cancel?.();
        closeFinancialConfirmModal();
    });

    financialModalClose?.addEventListener('click', () => {
        financialModalActions.cancel?.();
        closeFinancialConfirmModal();
    });

    financialModal?.addEventListener('cancel', (event) => {
        event.preventDefault();
        financialModalActions.cancel?.();
        closeFinancialConfirmModal();
    });

    moneyFields.forEach((field) => {
        field.addEventListener('input', () => formatMoneyField(field));
        formatMoneyField(field);
    });

    templateSelect?.addEventListener('change', () => {
        const typeChanged = syncTemplateDefaults();
        refreshContractFields({ isInit: false, source: typeChanged ? 'type' : 'template' });
    });

    titleInput?.addEventListener('input', () => {
        if (titleInput.value.trim() !== '') {
            lastAutoTitle = titleInput.value.trim();
        }
    });

    condominiumSelect?.addEventListener('change', () => {
        syncSyndicFromCondominium();
        filterUnitsForCondominium({ isInit: false });
        refreshContractFields({ isInit: false, source: 'condominium_id' });
    });

    syndicSelect?.addEventListener('change', () => {
        syndicTouched = true;
        refreshContractFields({ isInit: false, source: 'syndico_entity_id' });
    });

    unitSelect?.addEventListener('change', () => {
        syncLinkedEntitiesFromUnit({ isInit: false });
        refreshContractFields({ isInit: false, source: 'unit_id' });
    });

    typeSelect?.addEventListener('change', () => {
        refreshContractFields({ isInit: false, source: 'type' });
    });

    statusSelect?.addEventListener('change', () => {
        refreshContractFields({ isInit: false, source: 'status' });
    });

    indefiniteCheckbox?.addEventListener('change', () => {
        refreshContractFields({ isInit: false, source: 'indefinite_term' });
    });

    billingTypeSelect?.addEventListener('change', () => {
        refreshContractFields({ isInit: false, source: 'billing_type' });
    });

    paymentMethodSelect?.addEventListener('change', () => {
        refreshContractFields({ isInit: false, source: 'payment_method' });
    });

    generateFinancialCheckbox?.addEventListener('change', () => {
        refreshContractFields({ isInit: false, source: 'generate_financial_entries' });
    });

    adjustmentIndexInput?.addEventListener('input', () => {
        refreshContractFields({ isInit: false, source: 'adjustment_index' });
    });

    penaltyValueInput?.addEventListener('input', () => {
        refreshContractFields({ isInit: false, source: 'penalty_value' });
    });

    penaltyPercentageInput?.addEventListener('input', () => {
        refreshContractFields({ isInit: false, source: 'penalty_percentage' });
    });

    installmentInput?.addEventListener('input', () => {
        if (String(installmentInput.value || '').trim() === '') {
            refreshContractFields({ isInit: false, source: 'installment_quantity' });
            return;
        }

        const currentValue = Number.parseInt(installmentInput.value || '0', 10) || 0;
        if (normalize(billingTypeSelect?.value) === 'unica') {
            installmentInput.value = '1';
        } else if (normalize(billingTypeSelect?.value) === 'parcelada' && currentValue > 0 && currentValue < 2) {
            installmentInput.value = '2';
        }

        refreshContractFields({ isInit: false, source: 'installment_quantity' });
    });

    form.addEventListener('submit', handleSubmit);

    syncTemplateDefaults();
    syncLinkedEntitiesFromUnit({ isInit: true });
    filterUnitsForCondominium({ isInit: true });
    syncSyndicFromCondominium(true);
    refreshContractFields({ isInit: true, source: 'init' });

    if (previewButton && editor && editorInput) {
        previewButton.addEventListener('click', async () => {
            ensureEditorInput();
            const formData = new FormData(form);
            formData.set('content_html', editorInput.value);
            formData.delete('_method');
            formData.delete('generate_pdf_now');
            previewButton.disabled = true;
            previewButton.textContent = 'Carregando...';

            try {
                const response = await fetch(@json(route('contratos.preview.resolve')), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: formData,
                });
                const raw = await response.text();
                let data = {};

                try {
                    data = raw ? JSON.parse(raw) : {};
                } catch (parseError) {
                    throw new Error('A resposta do preview nao veio em JSON. Recarregue a pagina e tente novamente.');
                }

                if (!response.ok) {
                    throw new Error(data.message || 'Nao foi possivel carregar o preview.');
                }

                editor.innerHTML = data.html || '';
                ensureEditorInput();
            } catch (error) {
                window.alert(error.message || 'Nao foi possivel carregar o preview do contrato.');
            } finally {
                previewButton.disabled = false;
                previewButton.textContent = 'Carregar / atualizar preview';
            }
        });
    }
});
</script>
@endpush
