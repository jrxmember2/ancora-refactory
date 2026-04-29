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

<form method="post" action="{{ $mode === 'create' ? route('contratos.store') : route('contratos.update', $contract) }}" class="space-y-6" id="contract-form">
    @csrf
    @if($mode === 'edit')
        @method('PUT')
    @endif

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
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                        <select name="type" id="contract-type" required class="{{ $inputClass }}">
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
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente vinculado</label>
                        <select name="client_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected((int) $valueOf('client_id') === (int) $client->id)>{{ $client->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Condominio vinculado</label>
                        <select name="condominium_id" id="contract-condominium-id" class="{{ $inputClass }}">
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
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Sindico vinculado</label>
                        <select name="syndico_entity_id" id="contract-syndic-id" class="{{ $inputClass }}">
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
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Unidade vinculada</label>
                        <select name="unit_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected((int) $valueOf('unit_id') === (int) $unit->id)>
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
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" class="{{ $inputClass }}">
                            @foreach($statusLabels as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('status', 'rascunho') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de inicio</label>
                        <input type="date" name="start_date" value="{{ $dateValue('start_date') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de termino</label>
                        <input type="date" name="end_date" id="contract-end-date" value="{{ $dateValue('end_date') }}" class="{{ $inputClass }}">
                    </div>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="indefinite_term" id="contract-indefinite-term" value="1" @checked($valueOf('indefinite_term', true))>
                        Prazo indeterminado
                    </label>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor do contrato</label>
                        <input name="contract_value" value="{{ $moneyValue('contract_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor mensal</label>
                        <input name="monthly_value" value="{{ $moneyValue('monthly_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor total</label>
                        <input name="total_value" value="{{ $moneyValue('total_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de cobranca</label>
                        <select name="billing_type" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($billingTypes as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('billing_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Banco / conta</label>
                        <select name="financial_account_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($financialAccounts as $account)
                                <option value="{{ $account->id }}" @selected((int) $valueOf('financial_account_id') === (int) $account->id)>
                                    {{ $account->name }}{{ $account->bank_name ? ' · '.$account->bank_name : '' }}{{ $account->account_number ? ' · '.$account->account_number : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de pagamento</label>
                        <select name="payment_method" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($paymentMethods as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('payment_method') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Dia de vencimento</label>
                        <input type="number" min="1" max="31" name="due_day" value="{{ $valueOf('due_day') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Recorrencia</label>
                        <select name="recurrence" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($recurrenceOptions as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('recurrence') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Indice de reajuste</label>
                        <input name="adjustment_index" value="{{ $valueOf('adjustment_index') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Periodicidade de reajuste</label>
                        <select name="adjustment_periodicity" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($adjustmentPeriodicities as $key => $label)
                                <option value="{{ $key }}" @selected($valueOf('adjustment_periodicity') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Proximo reajuste</label>
                        <input type="date" name="next_adjustment_date" value="{{ $dateValue('next_adjustment_date') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa em valor</label>
                        <input name="penalty_value" value="{{ $moneyValue('penalty_value') }}" class="{{ $inputClass }}" placeholder="R$ 0,00" data-money>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa em %</label>
                        <input name="penalty_percentage" value="{{ $valueOf('penalty_percentage') ? number_format((float) $valueOf('penalty_percentage'), 2, ',', '.') : '' }}" class="{{ $inputClass }}" placeholder="0,00">
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
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="generate_financial_entries" value="1" @checked($valueOf('generate_financial_entries'))>
                        Gerar cobrancas automaticas no Financeiro 360
                    </label>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Centro de custo futuro</label>
                        <input name="cost_center_future" value="{{ $valueOf('cost_center_future') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Categoria financeira futura</label>
                        <input name="financial_category_future" value="{{ $valueOf('financial_category_future') }}" class="{{ $inputClass }}">
                    </div>
                    <div class="md:col-span-2 xl:col-span-3">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacoes internas</label>
                        <textarea name="notes" rows="4" class="{{ $textareaClass }}">{{ $valueOf('notes') }}</textarea>
                    </div>
                    <div class="md:col-span-2 xl:col-span-3">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacoes financeiras</label>
                        <textarea name="financial_notes" rows="4" class="{{ $textareaClass }}">{{ $valueOf('financial_notes') }}</textarea>
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

            @if($contract?->final_pdf_path)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-base font-semibold text-gray-900 dark:text-white">PDF atual</div>
                    <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">Ultimo PDF gerado em {{ optional($contract->final_pdf_generated_at)->format('d/m/Y H:i') ?: 'data nao informada' }}.</div>
                    <a href="{{ route('contratos.download-pdf', $contract) }}" class="mt-4 inline-flex rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Baixar PDF final</a>
                </div>
            @endif
        </aside>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ route('contratos.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</a>
        <button class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white">Salvar contrato</button>
        <button type="submit" name="generate_pdf_now" value="1" class="rounded-xl border border-success-300 bg-success-50 px-5 py-3 text-sm font-medium text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-200">Salvar e gerar PDF</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#contract-form');
    const previewButton = document.querySelector('#load-contract-preview');
    const editor = document.querySelector('[data-rich-editor="contract-content-editor"]');
    const input = document.querySelector('[data-rich-editor-input="contract-content-editor"]');
    const templateSelect = document.querySelector('#contract-template-id');
    const titleInput = document.querySelector('#contract-title');
    const typeSelect = document.querySelector('#contract-type');
    const condominiumSelect = document.querySelector('#contract-condominium-id');
    const syndicSelect = document.querySelector('#contract-syndic-id');
    const indefiniteCheckbox = document.querySelector('#contract-indefinite-term');
    const endDateInput = document.querySelector('#contract-end-date');

    let lastAutoTitle = titleInput ? titleInput.value.trim() : '';
    let syndicTouched = false;

    const normalizeEditorHtml = (html) => {
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
    };

    const formatMoneyField = (field) => {
        const digits = String(field.value || '').replace(/\D/g, '');
        if (!digits) {
            field.value = '';
            return;
        }

        const amount = Number(digits) / 100;
        field.value = `R$ ${amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    };

    const syncTemplateDefaults = () => {
        if (!templateSelect) {
            return;
        }

        const option = templateSelect.selectedOptions[0];
        if (!option) {
            return;
        }

        const nextTitle = (option.dataset.defaultTitle || '').trim();
        const nextType = (option.dataset.documentType || '').trim();

        if (titleInput && nextTitle !== '' && (titleInput.value.trim() === '' || titleInput.value.trim() === lastAutoTitle)) {
            titleInput.value = nextTitle;
            lastAutoTitle = nextTitle;
        }

        if (typeSelect && nextType !== '') {
            typeSelect.value = nextType;
        }
    };

    const syncSyndicFromCondominium = () => {
        if (!condominiumSelect || !syndicSelect || syndicTouched) {
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
    };

    const syncIndefiniteState = () => {
        if (!indefiniteCheckbox || !endDateInput) {
            return;
        }

        const disabled = indefiniteCheckbox.checked;
        endDateInput.disabled = disabled;
        if (disabled) {
            endDateInput.value = '';
        }
    };

    document.querySelectorAll('[data-money]').forEach((field) => {
        field.addEventListener('input', () => formatMoneyField(field));
        formatMoneyField(field);
    });

    templateSelect?.addEventListener('change', syncTemplateDefaults);
    titleInput?.addEventListener('input', () => {
        if (titleInput.value.trim() !== '') {
            lastAutoTitle = titleInput.value.trim();
        }
    });

    condominiumSelect?.addEventListener('change', syncSyndicFromCondominium);
    syndicSelect?.addEventListener('change', () => {
        syndicTouched = true;
    });

    indefiniteCheckbox?.addEventListener('change', syncIndefiniteState);

    syncTemplateDefaults();
    syncSyndicFromCondominium();
    syncIndefiniteState();

    if (previewButton && form && editor && input) {
        previewButton.addEventListener('click', async () => {
            input.value = normalizeEditorHtml(editor.innerHTML);
            const formData = new FormData(form);
            formData.set('content_html', input.value);
            formData.delete('_method');
            formData.delete('generate_pdf_now');
            previewButton.disabled = true;
            previewButton.textContent = 'Carregando...';

            try {
                const response = await fetch(@json(route('contratos.preview.resolve')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
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
                input.value = normalizeEditorHtml(editor.innerHTML);
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
