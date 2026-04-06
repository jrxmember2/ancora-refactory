@extends('layouts.app')

@section('content')
@php
    $fieldClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-400 focus:ring-4 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-brand-500 dark:focus:ring-brand-500/20';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-400 focus:ring-4 focus:ring-brand-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-brand-500 dark:focus:ring-brand-500/20';
    $labelClass = 'mb-2 block text-sm font-medium text-gray-700 dark:text-gray-200';
    $panelClass = 'rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]';
    $currentCode = old('proposal_code', $proposal?->proposal_code);
    $currentDate = old('proposal_date', optional($proposal?->proposal_date)->format('Y-m-d'));
    $currentSeqMap = $proposalSeqPreviewByYear ?? [];
@endphp

<x-ancora.section-header :title="$proposal ? 'Editar proposta' : 'Nova proposta'" subtitle="Monte a proposta com os dados principais, valores e acompanhamento comercial." />

@if(session('errors_list'))
    <div class="mb-6 rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-950/30 dark:text-error-300">
        <p class="font-semibold">Revise os campos abaixo:</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach((array) session('errors_list') as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr),320px]">
    <form method="post" action="{{ $action }}" class="space-y-6" id="proposal-form">
        @csrf
        @if($proposal)
            @method('put')
        @endif

        <div class="{{ $panelClass }}">
            <div class="flex flex-col gap-1 border-b border-gray-100 pb-5 dark:border-gray-800">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Campos centrais da proposta, com distribuição otimizada para leitura em tema claro e escuro.</p>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 2xl:grid-cols-3">
                <div>
                    <label class="{{ $labelClass }}">Número da proposta</label>
                    <input
                        type="text"
                        value="{{ $currentCode }}"
                        placeholder="Gerado automaticamente ao salvar"
                        class="{{ $fieldClass }} bg-gray-50 font-semibold text-brand-700 dark:bg-white/[0.05] dark:text-brand-300"
                        readonly
                        data-proposal-code-preview
                        data-current-code="{{ $proposal?->proposal_code ?? '' }}"
                    />
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Padrão esperado: 001.2026. O número é definido automaticamente com base no ano da data.</p>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Data</label>
                    <input
                        type="date"
                        name="proposal_date"
                        value="{{ $currentDate }}"
                        class="{{ $fieldClass }}"
                        data-proposal-date
                    />
                </div>

                <div>
                    <label class="{{ $labelClass }}">Validade da proposta</label>
                    <div class="relative">
                        <input
                            type="number"
                            name="validity_days"
                            min="1"
                            max="365"
                            step="1"
                            value="{{ old('validity_days', $proposal?->validity_days ?? 30) }}"
                            class="{{ $fieldClass }} pr-16"
                            placeholder="Ex.: 30"
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-4 inline-flex items-center text-xs font-medium uppercase tracking-[0.2em] text-gray-400">dias</span>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Cliente</label>
                    <input type="text" name="client_name" value="{{ old('client_name', $proposal?->client_name) }}" class="{{ $fieldClass }}" placeholder="Nome do cliente ou condomínio" />
                </div>

                <div>
                    <label class="{{ $labelClass }}">Administradora / Síndico</label>
                    <select name="administradora_id" class="{{ $fieldClass }}">
                        <option value="">Selecione</option>
                        @foreach($administradoras as $item)
                            <option value="{{ $item->id }}" @selected((int) old('administradora_id', $proposal?->administradora_id) === (int) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Serviço solicitado</label>
                    <select name="service_id" class="{{ $fieldClass }}">
                        <option value="">Selecione</option>
                        @foreach($servicos as $item)
                            <option value="{{ $item->id }}" @selected((int) old('service_id', $proposal?->service_id) === (int) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Forma de envio</label>
                    <select name="send_method_id" class="{{ $fieldClass }}">
                        <option value="">Selecione</option>
                        @foreach($formasEnvio as $item)
                            <option value="{{ $item->id }}" @selected((int) old('send_method_id', $proposal?->send_method_id) === (int) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Status de retorno</label>
                    <select name="response_status_id" class="{{ $fieldClass }}">
                        <option value="">Selecione</option>
                        @foreach($statusRetorno as $item)
                            <option value="{{ $item->id }}" @selected((int) old('response_status_id', $proposal?->response_status_id) === (int) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Data de follow-up</label>
                    <input type="date" name="followup_date" value="{{ old('followup_date', optional($proposal?->followup_date)->format('Y-m-d')) }}" class="{{ $fieldClass }}" />
                </div>
            </div>
        </div>

        <div class="{{ $panelClass }}">
            <div class="flex flex-col gap-1 border-b border-gray-100 pb-5 dark:border-gray-800">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Contato e valores</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Informações do solicitante e valores financeiros da proposta.</p>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 2xl:grid-cols-3">
                <div>
                    <label class="{{ $labelClass }}">Solicitante</label>
                    <input type="text" name="requester_name" value="{{ old('requester_name', $proposal?->requester_name) }}" class="{{ $fieldClass }}" placeholder="Nome de quem solicitou" />
                </div>

                <div>
                    <label class="{{ $labelClass }}">Telefone</label>
                    <input
                        type="text"
                        name="requester_phone"
                        value="{{ old('requester_phone', $proposal?->requester_phone) }}"
                        class="{{ $fieldClass }}"
                        placeholder="(27) 99999-9999"
                        data-phone-mask
                        inputmode="numeric"
                    />
                </div>

                <div>
                    <label class="{{ $labelClass }}">E-mail de contato</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $proposal?->contact_email) }}" class="{{ $fieldClass }}" placeholder="contato@exemplo.com" />
                </div>

                <div>
                    <label class="{{ $labelClass }}">Valor total da proposta</label>
                    <input type="text" name="proposal_total" value="{{ old('proposal_total', $proposal?->proposal_total) }}" class="{{ $fieldClass }}" placeholder="R$ 0,00" data-money-mask inputmode="decimal" />
                </div>

                <div>
                    <label class="{{ $labelClass }}">Valor total fechado</label>
                    <input type="text" name="closed_total" value="{{ old('closed_total', $proposal?->closed_total) }}" class="{{ $fieldClass }}" placeholder="R$ 0,00" data-money-mask inputmode="decimal" />
                </div>
            </div>
        </div>

        <div class="{{ $panelClass }}">
            <div class="flex flex-col gap-1 border-b border-gray-100 pb-5 dark:border-gray-800">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Relacionamento e observações</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Controle de indicação e registro livre para detalhes comerciais.</p>
            </div>

            <div class="mt-6 space-y-5">
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-[220px,minmax(0,1fr)] lg:items-start">
                    <div>
                        <label class="{{ $labelClass }}">Indicação</label>
                        <label class="inline-flex h-11 w-full items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-200">
                            <input type="checkbox" name="has_referral" value="1" @checked((bool) old('has_referral', $proposal?->has_referral)) data-referral-toggle>
                            Houve indicação
                        </label>
                    </div>

                    <div data-referral-name-wrapper>
                        <label class="{{ $labelClass }}">Nome da indicação</label>
                        <input type="text" name="referral_name" value="{{ old('referral_name', $proposal?->referral_name) }}" placeholder="Informe o nome da indicação" class="{{ $fieldClass }}" />
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Observação</label>
                    <textarea name="notes" rows="5" class="{{ $textareaClass }}" placeholder="Observações importantes sobre negociação, escopo, histórico ou próximos passos.">{{ old('notes', $proposal?->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-600">
                <i class="fa-solid fa-floppy-disk"></i>
                {{ $submitLabel }}
            </button>

            @if($proposal)
                <a href="{{ route('propostas.show', $proposal) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </a>
            @else
                <a href="{{ route('propostas.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </a>
            @endif
        </div>
    </form>

    <aside class="space-y-6">
        <div class="{{ $panelClass }} sticky top-6">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo do preenchimento</h3>
            <div class="mt-5 space-y-4 text-sm">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.04]">
                    <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Número automático</p>
                    <p class="mt-2 font-semibold text-gray-900 dark:text-white" data-proposal-code-summary>{{ $currentCode ?: 'Aguardando data da proposta' }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.04]">
                    <p class="text-xs uppercase tracking-[0.2em] text-gray-400">Regras desta tela</p>
                    <ul class="mt-3 space-y-2 text-gray-600 dark:text-gray-300">
                        <li>• validade entre 1 e 365 dias;</li>
                        <li>• telefone com máscara e DDD;</li>
                        <li>• valores em real brasileiro;</li>
                        <li>• indicação abre o nome da referência.</li>
                    </ul>
                </div>

                @if($proposal)
                    <form method="post" action="{{ route('propostas.delete', $proposal) }}" onsubmit="return confirm('Deseja excluir esta proposta?');">
                        @csrf
                        @method('delete')
                        <button class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-error-300 px-5 py-3 text-sm font-medium text-error-600 transition hover:bg-error-50 dark:border-error-800 dark:text-error-300 dark:hover:bg-error-950/30">
                            <i class="fa-solid fa-trash"></i>
                            Excluir proposta
                        </button>
                    </form>
                @else
                    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        O botão de exclusão ficará disponível após salvar a proposta pela primeira vez.
                    </div>
                @endif
            </div>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
function applyPhoneMask(input) {
    let value = input.value.replace(/\D/g, '').slice(0, 11);
    if (value.length > 10) {
        value = value.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    } else if (value.length > 6) {
        value = value.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else if (value.length > 2) {
        value = value.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    }
    input.value = value.trim();
}

function applyMoneyMask(input) {
    const digits = input.value.replace(/\D/g, '');
    if (!digits) {
        input.value = '';
        return;
    }

    const value = (Number(digits) / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    input.value = `R$ ${value}`;
}

function updateReferralVisibility() {
    const toggle = document.querySelector('[data-referral-toggle]');
    const wrapper = document.querySelector('[data-referral-name-wrapper]');
    if (!toggle || !wrapper) return;
    wrapper.style.display = toggle.checked ? '' : 'none';
}

function updateProposalCodePreview() {
    const dateInput = document.querySelector('[data-proposal-date]');
    const previewInput = document.querySelector('[data-proposal-code-preview]');
    const summary = document.querySelector('[data-proposal-code-summary]');
    if (!dateInput || !previewInput) return;

    const currentCode = previewInput.dataset.currentCode || '';
    if (currentCode) {
        previewInput.value = currentCode;
        if (summary) summary.textContent = currentCode;
        return;
    }

    const seqMap = @json($currentSeqMap);
    const rawDate = dateInput.value || '';
    const year = rawDate ? Number(rawDate.substring(0, 4)) : null;

    if (!year) {
        previewInput.value = '';
        previewInput.placeholder = 'Gerado automaticamente ao salvar';
        if (summary) summary.textContent = 'Aguardando data da proposta';
        return;
    }

    const nextSeq = Number(seqMap[String(year)] || 1);
    const code = `${String(nextSeq).padStart(3, '0')}.${year}`;
    previewInput.value = code;
    if (summary) summary.textContent = code;
}

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-phone-mask]')) {
        applyPhoneMask(event.target);
    }
    if (event.target.matches('[data-money-mask]')) {
        applyMoneyMask(event.target);
    }
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-referral-toggle]')) {
        updateReferralVisibility();
    }
    if (event.target.matches('[data-proposal-date]')) {
        updateProposalCodePreview();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-phone-mask]').forEach(applyPhoneMask);
    document.querySelectorAll('[data-money-mask]').forEach(applyMoneyMask);
    updateReferralVisibility();
    updateProposalCodePreview();
});
</script>
@endpush
