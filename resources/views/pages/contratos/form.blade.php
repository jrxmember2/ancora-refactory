@extends('layouts.app')

@php
    $contract = $item;
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $valueOf = fn ($key, $fallback = null) => old($key, $contract?->{$key} ?? $draft[$key] ?? $fallback);
@endphp

@section('content')
<x-ancora.section-header :title="$mode === 'create' ? 'Novo contrato' : (($contract?->code ?: 'Editar contrato'))" subtitle="Cadastre contratos, termos, aditivos e demais instrumentos com preview editável e versionamento em PDF.">
    <a href="{{ route('contratos.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
</x-ancora.section-header>

<form method="post" action="{{ $mode === 'create' ? route('contratos.store') : route('contratos.update', $contract) }}" class="space-y-6" id="contract-form">
    @csrf
    @if($mode === 'edit')
        @method('PUT')
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
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Código interno</label>
                        <input name="code" value="{{ $valueOf('code') }}" class="{{ $inputClass }}" placeholder="Automático ou manual">
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Título do contrato</label>
                        <input name="title" value="{{ $valueOf('title') }}" required class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                        <select name="type" required class="{{ $inputClass }}">
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
                                <option value="{{ $template->id }}" @selected((int) $valueOf('template_id') === (int) $template->id)>{{ $template->name }}</option>
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
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Condomínio vinculado</label>
                        <select name="condominium_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($condominiums as $condominium)
                                <option value="{{ $condominium->id }}" @selected((int) $valueOf('condominium_id') === (int) $condominium->id)>{{ $condominium->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Unidade vinculada</label>
                        <select name="unit_id" class="{{ $inputClass }}">
                            <option value="">Selecione</option>
                            @foreach($units as $unit)
                                <option value="{{ $unit->id }}" @selected((int) $valueOf('unit_id') === (int) $unit->id)>
                                    {{ $unit->condominium?->name ?: 'Condomínio' }}{{ $unit->block?->name ? ' · '.$unit->block->name : '' }} · Unidade {{ $unit->unit_number }}
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
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label><select name="status" class="{{ $inputClass }}">@foreach($statusLabels as $key => $label)<option value="{{ $key }}" @selected($valueOf('status', 'rascunho') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de início</label><input type="date" name="start_date" value="{{ optional($valueOf('start_date'))->format('Y-m-d') ?? $valueOf('start_date') }}" class="{{ $inputClass }}"></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de término</label><input type="date" name="end_date" value="{{ optional($valueOf('end_date'))->format('Y-m-d') ?? $valueOf('end_date') }}" class="{{ $inputClass }}"></div>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200"><input type="checkbox" name="indefinite_term" value="1" @checked($valueOf('indefinite_term'))> Prazo indeterminado</label>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor do contrato</label><input name="contract_value" value="{{ $valueOf('contract_value') ? number_format((float) $valueOf('contract_value'), 2, ',', '.') : '' }}" class="{{ $inputClass }}" placeholder="0,00" data-money></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor mensal</label><input name="monthly_value" value="{{ $valueOf('monthly_value') ? number_format((float) $valueOf('monthly_value'), 2, ',', '.') : '' }}" class="{{ $inputClass }}" placeholder="0,00" data-money></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor total</label><input name="total_value" value="{{ $valueOf('total_value') ? number_format((float) $valueOf('total_value'), 2, ',', '.') : '' }}" class="{{ $inputClass }}" placeholder="0,00" data-money></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de cobrança</label><select name="billing_type" class="{{ $inputClass }}"><option value="">Selecione</option>@foreach($billingTypes as $key => $label)<option value="{{ $key }}" @selected($valueOf('billing_type') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Dia de vencimento</label><input type="number" min="1" max="31" name="due_day" value="{{ $valueOf('due_day') }}" class="{{ $inputClass }}"></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Recorrência</label><select name="recurrence" class="{{ $inputClass }}"><option value="">Selecione</option>@foreach($recurrenceOptions as $key => $label)<option value="{{ $key }}" @selected($valueOf('recurrence') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Índice de reajuste</label><input name="adjustment_index" value="{{ $valueOf('adjustment_index') }}" class="{{ $inputClass }}"></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Periodicidade de reajuste</label><select name="adjustment_periodicity" class="{{ $inputClass }}"><option value="">Selecione</option>@foreach($adjustmentPeriodicities as $key => $label)<option value="{{ $key }}" @selected($valueOf('adjustment_periodicity') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Próximo reajuste</label><input type="date" name="next_adjustment_date" value="{{ optional($valueOf('next_adjustment_date'))->format('Y-m-d') ?? $valueOf('next_adjustment_date') }}" class="{{ $inputClass }}"></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa em valor</label><input name="penalty_value" value="{{ $valueOf('penalty_value') ? number_format((float) $valueOf('penalty_value'), 2, ',', '.') : '' }}" class="{{ $inputClass }}" placeholder="0,00" data-money></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa em %</label><input name="penalty_percentage" value="{{ $valueOf('penalty_percentage') ? number_format((float) $valueOf('penalty_percentage'), 2, ',', '.') : '' }}" class="{{ $inputClass }}" placeholder="0,00"></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Responsável</label><select name="responsible_user_id" class="{{ $inputClass }}"><option value="">Selecione</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((int) $valueOf('responsible_user_id') === (int) $user->id)>{{ $user->name }}</option>@endforeach</select></div>
                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200"><input type="checkbox" name="generate_financial_entries" value="1" @checked($valueOf('generate_financial_entries'))> Preparar integração financeira futura</label>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Centro de custo futuro</label><input name="cost_center_future" value="{{ $valueOf('cost_center_future') }}" class="{{ $inputClass }}"></div>
                    <div><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Categoria financeira futura</label><input name="financial_category_future" value="{{ $valueOf('financial_category_future') }}" class="{{ $inputClass }}"></div>
                    <div class="md:col-span-2 xl:col-span-3"><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações internas</label><textarea name="notes" rows="4" class="{{ $textareaClass }}">{{ $valueOf('notes') }}</textarea></div>
                    <div class="md:col-span-2 xl:col-span-3"><label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações financeiras</label><textarea name="financial_notes" rows="4" class="{{ $textareaClass }}">{{ $valueOf('financial_notes') }}</textarea></div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Etapa 3</div>
                        <h3 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Preview editável do contrato</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Escolha o template, carregue as variáveis e ajuste o texto antes de salvar ou gerar a versão final.</p>
                    </div>
                    <button type="button" id="load-contract-preview" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Carregar / atualizar preview</button>
                </div>
                <div class="mt-5">
                    @include('pages.contratos.partials.rich-editor', [
                        'editorId' => 'contract-content-editor',
                        'name' => 'content_html',
                        'value' => $previewHtml,
                        'placeholder' => 'Carregue o template e ajuste as cláusulas aqui.',
                        'minHeight' => '420px',
                    ])
                </div>
                <div class="mt-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observação da versão</label>
                    <input name="version_notes" value="{{ old('version_notes') }}" class="{{ $inputClass }}" placeholder="Ex.: versão inicial, ajuste de cláusula quinta, atualização de valores...">
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="text-base font-semibold text-gray-900 dark:text-white">Etapa 4 — Finalização</div>
                <div class="mt-4 space-y-4 text-sm text-gray-600 dark:text-gray-300">
                    <p>Salve em rascunho, ajuste o preview e gere o PDF final somente quando o texto estiver validado.</p>
                    <p>O PDF gerado cria automaticamente uma nova versão no histórico do contrato.</p>
                    <p>Se o template estiver vazio, o sistema usa o conteúdo atualmente editado para atualizar as variáveis informadas.</p>
                </div>
            </div>

            @if($contract?->final_pdf_path)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-base font-semibold text-gray-900 dark:text-white">PDF atual</div>
                    <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">Último PDF gerado em {{ optional($contract->final_pdf_generated_at)->format('d/m/Y H:i') ?: 'data não informada' }}.</div>
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

    const formatMoneyField = (field) => {
        const digits = String(field.value || '').replace(/\D/g, '');
        if (!digits) {
            field.value = '';
            return;
        }

        const amount = Number(digits) / 100;
        field.value = amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    document.querySelectorAll('[data-money]').forEach((field) => {
        field.addEventListener('input', () => formatMoneyField(field));
        formatMoneyField(field);
    });

    if (previewButton && form && editor && input) {
        previewButton.addEventListener('click', async () => {
            input.value = editor.innerHTML.trim();
            const formData = new FormData(form);
            previewButton.disabled = true;
            previewButton.textContent = 'Carregando...';

            try {
                const response = await fetch(@json(route('contratos.preview.resolve')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: formData,
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Não foi possível carregar o preview.');
                }

                editor.innerHTML = data.html || '';
                input.value = editor.innerHTML.trim();
            } catch (error) {
                window.alert(error.message || 'Não foi possível carregar o preview do contrato.');
            } finally {
                previewButton.disabled = false;
                previewButton.textContent = 'Carregar / atualizar preview';
            }
        });
    }
});
</script>
@endpush
