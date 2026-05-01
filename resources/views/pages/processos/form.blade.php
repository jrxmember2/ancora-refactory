@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]';
@endphp

@section('content')
<x-ancora.section-header :title="$case ? 'Editar processo' : 'Novo processo'" subtitle="Cadastro central do processo, valores, descricao e encerramento.">
    <a href="{{ $case ? route('processos.show', $case) : route('processos.index') }}" class="{{ $softButtonClass }}">Voltar</a>
</x-ancora.section-header>

@if($errors->any())
    <div class="mb-6 rounded-2xl border border-error-200 bg-error-50 p-4 text-sm text-error-700 dark:border-error-900/60 dark:bg-error-500/10 dark:text-error-200">
        Revise os campos destacados antes de salvar.
    </div>
@endif

<form method="post" action="{{ $action }}" class="space-y-6" id="process-form">
    @csrf
    @if($case)
        @method('PUT')
    @endif

    <datalist id="process-entities">
        @foreach($entities as $entity)
            <option value="{{ $entity->display_name }}">{{ $entity->cpf_cnpj }} {{ $entity->legal_name }}</option>
        @endforeach
    </datalist>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Principal</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Dados essenciais do processo e partes envolvidas.</p>
            </div>
            <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                <input type="checkbox" name="is_private" value="1" @checked((bool) ($formData['is_private'] ?? false))>
                Particular
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-500 dark:bg-gray-800" title="Processos particulares aparecem somente para o responsavel, para quem cadastrou e para superadmins.">?</span>
            </label>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Advogado responsavel</label>
                <input name="responsible_lawyer" value="{{ $formData['responsible_lawyer'] }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de abertura</label>
                <input type="date" name="opened_at" value="{{ $formData['opened_at'] }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Processo</label>
                <input name="process_number" value="{{ $formData['process_number'] }}" class="{{ $inputClass }}" data-process-mask placeholder="0000000-00.0000.0.00.0000">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tribunal DataJud</label>
                <select name="datajud_court" class="{{ $inputClass }}">
                    <option value="">Nao sincronizar</option>
                    @foreach(($options['datajud_court'] ?? collect()) as $option)
                        <option value="{{ $option->slug }}" @selected(($formData['datajud_court'] ?? '') === $option->slug)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="status_option_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach(($options['status'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((int) ($formData['status_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de acao</label>
                <select name="action_type_option_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach(($options['action_type'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((int) ($formData['action_type_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de processo</label>
                <select name="process_type_option_id" class="{{ $inputClass }}" data-process-type-select>
                    <option value="">Selecione</option>
                    @foreach(($options['process_type'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" data-process-type-slug="{{ \Illuminate\Support\Str::slug($option->name) }}" @selected((int) ($formData['process_type_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Natureza</label>
                <select name="nature_option_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach(($options['nature'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((int) ($formData['nature_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hidden" data-judging-body-wrapper>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" data-judging-body-label>Vara/Orgao/Setor</label>
                <input name="judging_body" value="{{ $formData['judging_body'] }}" class="{{ $inputClass }}" data-judging-body-input>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
                <input name="client_name" list="process-entities" value="{{ $formData['client_name'] }}" class="{{ $inputClass }}" placeholder="Pesquise ou digite o cliente">
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Vinculo com condominio no portal</label>
                <select name="client_condominium_id" class="{{ $inputClass }}">
                    <option value="">Nao vincular a condominio</option>
                    @foreach($condominiums as $condominium)
                        <option value="{{ $condominium->id }}" @selected((int) ($formData['client_condominium_id'] ?? 0) === (int) $condominium->id)>{{ $condominium->name }}{{ $condominium->cnpj ? ' - '.$condominium->cnpj : '' }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use este campo para liberar a visualizacao segura do processo no Portal do Cliente do condominio.</p>
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Adverso</label>
                <input name="adverse_name" list="process-entities" value="{{ $formData['adverse_name'] }}" class="{{ $inputClass }}" placeholder="Pesquise ou digite um nome livre">
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Vinculo do adverso com condominio no portal</label>
                <select name="adverse_condominium_id" class="{{ $inputClass }}">
                    <option value="">Nao vincular adverso a condominio</option>
                    @foreach($condominiums as $condominium)
                        <option value="{{ $condominium->id }}" @selected((int) ($formData['adverse_condominium_id'] ?? 0) === (int) $condominium->id)>{{ $condominium->name }}{{ $condominium->cnpj ? ' - '.$condominium->cnpj : '' }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use este campo quando o condominio estiver no polo adverso, mas ainda precisar visualizar o processo no Portal do Cliente.</p>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Posicao do cliente</label>
                <select name="client_position_option_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach(($options['client_position'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((int) ($formData['client_position_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Posicao do adverso</label>
                <select name="adverse_position_option_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach(($options['adverse_position'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((int) ($formData['adverse_position_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Advogado do cliente</label>
                <input name="client_lawyer" value="{{ $formData['client_lawyer'] }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Advogado do adverso</label>
                <input name="adverse_lawyer" value="{{ $formData['adverse_lawyer'] }}" class="{{ $inputClass }}">
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Valores</h3>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['claim_amount', 'claim_amount_date', 'Valor da causa'],
                ['provisioned_amount', 'provisioned_amount_date', 'Valor provisionado'],
                ['court_paid_amount', 'court_paid_amount_date', 'Total pago em juizo'],
                ['process_cost_amount', 'process_cost_amount_date', 'Custo do processo'],
                ['sentence_amount', 'sentence_amount_date', 'Valor da sentenca'],
            ] as [$amountField, $dateField, $label])
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
                    <input name="{{ $amountField }}" value="{{ $formData[$amountField] }}" class="{{ $inputClass }}" data-money-mask placeholder="0,00">
                    <input type="date" name="{{ $dateField }}" value="{{ $formData[$dateField] }}" class="{{ $inputClass }} mt-2">
                </div>
            @endforeach
            <div class="md:col-span-2 xl:col-span-5">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Possibilidade de ganho</label>
                <select name="win_probability_option_id" class="{{ $inputClass }} max-w-md">
                    <option value="">Selecione</option>
                    @foreach(($options['win_probability'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((int) ($formData['win_probability_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Descricao</h3>
        <textarea name="notes" rows="6" class="{{ $textareaClass }} mt-4" placeholder="Observacoes gerais do processo">{{ $formData['notes'] }}</textarea>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Encerramento</h3>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data de encerramento</label>
                <input type="date" name="closed_at" value="{{ $formData['closed_at'] }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Responsavel pelo encerramento</label>
                <input name="closed_by" value="{{ $formData['closed_by'] }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo de encerramento</label>
                <select name="closure_type_option_id" class="{{ $inputClass }}">
                    <option value="">Selecione</option>
                    @foreach(($options['closure_type'] ?? collect()) as $option)
                        <option value="{{ $option->id }}" @selected((int) ($formData['closure_type_option_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <textarea name="closure_notes" rows="4" class="{{ $textareaClass }} mt-4" placeholder="Observacoes do encerramento">{{ $formData['closure_notes'] }}</textarea>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <a href="{{ $case ? route('processos.show', $case) : route('processos.index') }}" onclick="return confirm('Cancelar sem salvar as alteracoes?')" class="{{ $softButtonClass }}">Cancelar</a>
        <button name="_next" value="phase" class="{{ $softButtonClass }}">Salvar e nova fase</button>
        <button class="{{ $buttonClass }}">Salvar</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function processMaskValue(value) {
    const digits = value.replace(/\D/g, '').slice(0, 20);
    if (digits.length < 20) return value;
    return digits.replace(/(\d{7})(\d{2})(\d{4})(\d{1})(\d{2})(\d{4})/, '$1-$2.$3.$4.$5.$6');
}

function moneyMaskValue(value) {
    const digits = value.replace(/\D/g, '');
    if (!digits) return '';
    const cents = Number(digits) / 100;
    return cents.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-process-mask]')) {
        const masked = processMaskValue(event.target.value);
        if (masked !== event.target.value) event.target.value = masked;
    }
    if (event.target.matches('[data-money-mask]')) {
        event.target.value = moneyMaskValue(event.target.value);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.querySelector('[data-process-type-select]');
    const wrapper = document.querySelector('[data-judging-body-wrapper]');
    const label = document.querySelector('[data-judging-body-label]');
    const input = document.querySelector('[data-judging-body-input]');

    if (!typeSelect || !wrapper || !label || !input) {
        return;
    }

    const syncJudgingBodyField = () => {
        const selected = typeSelect.options[typeSelect.selectedIndex];
        const slug = selected?.dataset.processTypeSlug || '';
        const hasValue = input.value.trim() !== '';

        if (slug === 'administrativo') {
            label.textContent = 'Orgao/Setor';
            wrapper.classList.remove('hidden');
            return;
        }

        if (slug === 'judicial') {
            label.textContent = 'Vara/Setor';
            wrapper.classList.remove('hidden');
            return;
        }

        if (hasValue) {
            label.textContent = 'Vara/Orgao/Setor';
            wrapper.classList.remove('hidden');
            return;
        }

        wrapper.classList.add('hidden');
    };

    typeSelect.addEventListener('change', syncJudgingBodyField);
    input.addEventListener('input', syncJudgingBodyField);
    syncJudgingBodyField();
});
</script>
@endpush
