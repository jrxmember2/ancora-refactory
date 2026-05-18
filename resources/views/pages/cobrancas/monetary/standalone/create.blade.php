@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $quotaRows = collect(old('quotas', [[
        'selected' => '1',
        'reference_label' => '',
        'due_date' => '',
        'original_amount' => '',
    ]]))->values();
    if ($quotaRows->isEmpty()) {
        $quotaRows = collect([[
            'selected' => '1',
            'reference_label' => '',
            'due_date' => '',
            'original_amount' => '',
        ]]);
    }
@endphp

@section('content')
<x-ancora.section-header title="Novo TJES avulso" subtitle="Calcule debitos fora da estrutura condominial usando a mesma logica, os mesmos fatores TJES e a mesma memoria de calculo do modulo de cobrancas.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.monetary.standalone.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

@if(!($storageReady ?? false))
    <div class="rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
        Rode a migration do TJES avulso antes de criar memorias fora da OS.
    </div>
@else
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr,0.9fr]">
        <form id="standalone-monetary-form" method="post" action="{{ route('cobrancas.monetary.standalone.store') }}" class="space-y-6">
            @csrf

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Identificacao e devedor</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Vincule um cliente avulso quando houver cadastro. Se preferir, preencha os dados manualmente.</p>
                    </div>
                    <button type="button" id="fill-client-data" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Preencher do cadastro</button>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Identificacao interna</label>
                        <input name="title" value="{{ old('title') }}" class="{{ $inputClass }}" placeholder="Opcional. Se ficar vazio, o sistema gera um titulo automatico.">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente avulso vinculado</label>
                        <select name="client_entity_id" id="standalone-client-select" class="{{ $inputClass }}">
                            <option value="">Nao vincular</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected((int) old('client_entity_id') === (int) $client->id)>{{ $client->display_name ?: $client->legal_name ?: ('Cliente #' . $client->id) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome do devedor</label>
                        <input name="debtor_name_snapshot" id="standalone-debtor-name" value="{{ old('debtor_name_snapshot') }}" class="{{ $inputClass }}" placeholder="Nome completo ou razao social">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CPF/CNPJ</label>
                        <input name="debtor_document_snapshot" id="standalone-debtor-document" value="{{ old('debtor_document_snapshot') }}" class="{{ $inputClass }}" placeholder="000.000.000-00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                        <input type="email" name="debtor_email_snapshot" id="standalone-debtor-email" value="{{ old('debtor_email_snapshot') }}" class="{{ $inputClass }}" placeholder="email@dominio.com">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone</label>
                        <input name="debtor_phone_snapshot" id="standalone-debtor-phone" value="{{ old('debtor_phone_snapshot') }}" class="{{ $inputClass }}" placeholder="(27) 99999-9999" data-phone-mask>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacoes</label>
                        <textarea name="description" rows="3" class="{{ $textareaClass }}" placeholder="Observacoes internas opcionais para esta memoria avulsa.">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Debitos considerados</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cada linha funciona como uma quota da memoria TJES. Marque apenas o que deve entrar no calculo.</p>
                    </div>
                    <button type="button" id="add-standalone-quota" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10"><i class="fa-solid fa-plus"></i></button>
                </div>

                <div id="standalone-quota-rows" class="mt-5 space-y-3">
                    @foreach($quotaRows as $index => $row)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800" data-standalone-quota-row>
                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[140px_minmax(0,1fr)_180px_180px_120px]">
                                <div class="flex items-end">
                                    <label class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                        <input type="hidden" name="quotas[{{ $index }}][selected]" value="0">
                                        <input type="checkbox" name="quotas[{{ $index }}][selected]" value="1" @checked((string) ($row['selected'] ?? '1') === '1')>
                                        Considerar
                                    </label>
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Competencia / referencia</label>
                                    <input type="text" name="quotas[{{ $index }}][reference_label]" value="{{ $row['reference_label'] ?? '' }}" placeholder="mm/aaaa" class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vencimento</label>
                                    <input type="date" name="quotas[{{ $index }}][due_date]" value="{{ $row['due_date'] ?? '' }}" class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor original</label>
                                    <input type="text" name="quotas[{{ $index }}][original_amount]" value="{{ $row['original_amount'] ?? '' }}" placeholder="0,00" class="{{ $inputClass }}" data-decimal-mask>
                                </div>
                                <div class="flex items-end">
                                    <button type="button" class="h-11 w-full rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-remove-standalone-quota>Remover</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Parametros do calculo</h3>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data final do calculo</label>
                        <input type="date" name="final_date" value="{{ old('final_date', $defaultFinalDate) }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Indice</label>
                        <select name="index_code" class="{{ $inputClass }}">
                            <option value="ATM" @selected(old('index_code', 'ATM') === 'ATM')>Indice do TJES</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Juros moratorios</label>
                        <select name="interest_type" id="standalone-interest-type" class="{{ $inputClass }}">
                            <option value="legal" @selected(old('interest_type', 'legal') === 'legal')>Juros legais</option>
                            <option value="contractual" @selected(old('interest_type') === 'contractual')>Juros contratuais</option>
                            <option value="none" @selected(old('interest_type') === 'none')>Sem juros</option>
                        </select>
                    </div>
                    <div id="standalone-interest-rate-wrap" class="{{ old('interest_type') === 'contractual' ? '' : 'hidden' }}">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Percentual mensal</label>
                        <input type="text" name="interest_rate_monthly" value="{{ old('interest_rate_monthly', '1,00') }}" class="{{ $inputClass }}" data-decimal-mask>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Multa (%)</label>
                        <input type="text" name="fine_percent" value="{{ old('fine_percent', '2,00') }}" class="{{ $inputClass }}" data-decimal-mask>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Honorarios por</label>
                        <select name="attorney_fee_type" id="standalone-attorney-fee-type" class="{{ $inputClass }}">
                            <option value="percent" @selected(old('attorney_fee_type', 'percent') === 'percent')>Percentual (%)</option>
                            <option value="fixed" @selected(old('attorney_fee_type') === 'fixed')>Valor fixo (R$)</option>
                            <option value="none" @selected(old('attorney_fee_type') === 'none')>Sem honorarios</option>
                        </select>
                    </div>
                    <div>
                        <label id="standalone-attorney-fee-label" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Honorarios (%)</label>
                        <input type="text" name="attorney_fee_value" value="{{ old('attorney_fee_value', '10,00') }}" class="{{ $inputClass }}" data-decimal-mask>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Custas/despesas (R$)</label>
                        <input type="text" name="costs_amount" value="{{ old('costs_amount') }}" class="{{ $inputClass }}" data-decimal-mask placeholder="0,00">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data das custas</label>
                        <input type="date" name="costs_date" value="{{ old('costs_date') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Abatimento na data final (R$)</label>
                        <input type="text" name="abatement_amount" value="{{ old('abatement_amount') }}" class="{{ $inputClass }}" data-decimal-mask placeholder="0,00">
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="apply_boleto_fee" value="1" @checked(old('apply_boleto_fee'))>
                            Aplicar taxa de boleto
                        </label>
                        <input type="text" name="boleto_fee_amount" value="{{ old('boleto_fee_amount') }}" class="{{ $inputClass }} mt-3" data-decimal-mask placeholder="Valor unitario por debito">
                    </div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="apply_boleto_cancellation_fee" value="1" @checked(old('apply_boleto_cancellation_fee'))>
                            Aplicar taxa de cancelamento de boleto
                        </label>
                        <input type="text" name="boleto_cancellation_fee_amount" value="{{ old('boleto_cancellation_fee_amount') }}" class="{{ $inputClass }} mt-3" data-decimal-mask placeholder="Valor unitario por debito">
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('cobrancas.monetary.standalone.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</a>
                <button type="submit" class="rounded-xl bg-warning-500 px-4 py-3 text-sm font-medium text-white hover:bg-warning-600">Salvar memoria avulsa</button>
            </div>
        </form>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Previa da memoria</h3>
                        <p id="standalone-preview-message" class="mt-1 text-sm text-gray-500 dark:text-gray-400">Clique em Simular calculo para conferir fatores, juros, multa e total geral antes de salvar.</p>
                    </div>
                    <button type="button" id="standalone-preview-button" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Simular calculo</button>
                </div>

                <div id="standalone-preview-empty" class="mt-5 rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    A memoria detalhada aparecera aqui com o mesmo motor de calculo TJES usado nas OS.
                </div>

                <div id="standalone-preview-content" class="mt-5 hidden space-y-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Debito atualizado</div>
                            <div id="standalone-preview-debit-total" class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">R$ 0,00</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Honorarios</div>
                            <div id="standalone-preview-attorney-fee" class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">R$ 0,00</div>
                        </div>
                        <div class="rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-800/70 dark:bg-warning-500/10">
                            <div class="text-xs uppercase tracking-[0.16em] text-warning-700 dark:text-warning-300">Total geral</div>
                            <div id="standalone-preview-grand-total" class="mt-2 text-lg font-semibold text-warning-900 dark:text-warning-100">R$ 0,00</div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-gray-800">
                        <table class="min-w-full text-left text-xs">
                            <thead class="border-b border-gray-100 bg-gray-50 text-[11px] uppercase tracking-[0.14em] text-gray-500 dark:border-gray-800 dark:bg-gray-950/30 dark:text-gray-400">
                                <tr>
                                    <th class="px-3 py-3">Ref.</th>
                                    <th class="px-3 py-3">Venc.</th>
                                    <th class="px-3 py-3">Original</th>
                                    <th class="px-3 py-3">Fator</th>
                                    <th class="px-3 py-3">Corrigido</th>
                                    <th class="px-3 py-3">Juros</th>
                                    <th class="px-3 py-3">Multa</th>
                                    <th class="px-3 py-3">Total</th>
                                </tr>
                            </thead>
                            <tbody id="standalone-preview-items" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
                        </table>
                    </div>

                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Valor original</span><strong id="preview-total-original" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Principal corrigido</span><strong id="preview-total-corrected" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Juros</span><strong id="preview-total-interest" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Multa</span><strong id="preview-total-fine" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Custas corrigidas</span><strong id="preview-total-costs" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Taxa de boleto</span><strong id="preview-total-boleto-fee" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Taxa cancel. boleto</span><strong id="preview-total-boleto-cancel" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                            <div class="flex items-center justify-between gap-3 text-sm"><span class="text-gray-500 dark:text-gray-400">Abatimento</span><strong id="preview-total-abatement" class="text-gray-900 dark:text-white">R$ 0,00</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Como vai funcionar</h3>
                <ol class="mt-5 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                    <li>1. O calculo usa os mesmos fatores mensais ATM/TJES cadastrados em Configuracoes.</li>
                    <li>2. Cada debito avulso entra como item independente da memoria, com referencia, vencimento e valor original.</li>
                    <li>3. O resultado fica salvo fora da OS, com PDF proprio e historico proprio dentro de Cobranças.</li>
                    <li>4. Nada do fluxo atual de OS e aplicacao na cobrança existente e alterado.</li>
                </ol>
            </div>
        </div>
    </div>

    <script id="standalone-client-data" type="application/json">
        @json($clients->map(fn ($client) => [
            'id' => $client->id,
            'name' => $client->display_name ?: $client->legal_name,
            'document' => $client->cpf_cnpj,
            'email' => strtolower(trim((string) data_get(collect($client->emails_json ?? [])->first(), 'email'))),
            'phone' => collect($client->phones_json ?? [])->map(fn ($row) => is_array($row) ? ($row['number'] ?? '') : (string) $row)->filter()->first(),
        ])->values())
    </script>
@endif
@endsection

@if($storageReady ?? false)
@push('scripts')
<script>
(() => {
    const form = document.getElementById('standalone-monetary-form');
    if (!form) return;

    const previewUrl = @json(route('cobrancas.monetary.standalone.preview'));
    const clientData = JSON.parse(document.getElementById('standalone-client-data')?.textContent || '[]');
    const clientMap = new Map(clientData.map((item) => [String(item.id), item]));
    const clientSelect = document.getElementById('standalone-client-select');
    const fillClientDataButton = document.getElementById('fill-client-data');
    const debtorName = document.getElementById('standalone-debtor-name');
    const debtorDocument = document.getElementById('standalone-debtor-document');
    const debtorEmail = document.getElementById('standalone-debtor-email');
    const debtorPhone = document.getElementById('standalone-debtor-phone');
    const addQuotaButton = document.getElementById('add-standalone-quota');
    const quotaContainer = document.getElementById('standalone-quota-rows');
    const previewButton = document.getElementById('standalone-preview-button');
    const previewMessage = document.getElementById('standalone-preview-message');
    const previewEmpty = document.getElementById('standalone-preview-empty');
    const previewContent = document.getElementById('standalone-preview-content');
    const previewItems = document.getElementById('standalone-preview-items');
    const previewDebitTotal = document.getElementById('standalone-preview-debit-total');
    const previewAttorneyFee = document.getElementById('standalone-preview-attorney-fee');
    const previewGrandTotal = document.getElementById('standalone-preview-grand-total');
    const interestType = document.getElementById('standalone-interest-type');
    const interestRateWrap = document.getElementById('standalone-interest-rate-wrap');
    const attorneyFeeType = document.getElementById('standalone-attorney-fee-type');
    const attorneyFeeLabel = document.getElementById('standalone-attorney-fee-label');
    const attorneyFeeInput = form.querySelector('[name="attorney_fee_value"]');

    let quotaIndex = quotaContainer.querySelectorAll('[data-standalone-quota-row]').length;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>\"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function formatDecimalValue(value) {
        const digits = onlyDigits(value);
        if (!digits) return '';
        return (Number(digits) / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function bindDecimalMasks(scope = form) {
        scope.querySelectorAll('[data-decimal-mask]').forEach((input) => {
            if (input.dataset.decimalBound === '1') return;
            input.dataset.decimalBound = '1';
            input.value = formatDecimalValue(input.value);
            input.addEventListener('input', () => {
                input.value = formatDecimalValue(input.value);
            });
            input.addEventListener('blur', () => {
                input.value = formatDecimalValue(input.value);
            });
        });
    }

    function updateConditionalFields() {
        if (interestRateWrap && interestType) {
            interestRateWrap.classList.toggle('hidden', interestType.value !== 'contractual');
        }

        if (!attorneyFeeType || !attorneyFeeInput || !attorneyFeeLabel) return;

        if (attorneyFeeType.value === 'fixed') {
            attorneyFeeLabel.textContent = 'Honorarios (R$)';
            attorneyFeeInput.disabled = false;
            attorneyFeeInput.placeholder = '0,00';
            return;
        }

        if (attorneyFeeType.value === 'none') {
            attorneyFeeLabel.textContent = 'Honorarios';
            attorneyFeeInput.value = '';
            attorneyFeeInput.disabled = true;
            attorneyFeeInput.placeholder = 'Sem honorarios';
            return;
        }

        attorneyFeeLabel.textContent = 'Honorarios (%)';
        attorneyFeeInput.disabled = false;
        attorneyFeeInput.placeholder = '0,00';
        if (!attorneyFeeInput.value) attorneyFeeInput.value = '10,00';
    }

    function fillClientData(force = false) {
        const client = clientMap.get(String(clientSelect?.value || ''));
        if (!client) return;

        if (force || !debtorName.value) debtorName.value = client.name || '';
        if (force || !debtorDocument.value) debtorDocument.value = client.document || '';
        if (force || !debtorEmail.value) debtorEmail.value = client.email || '';
        if (force || !debtorPhone.value) debtorPhone.value = client.phone || '';
    }

    function createQuotaRow(index) {
        const wrapper = document.createElement('div');
        wrapper.className = 'rounded-xl border border-gray-200 p-4 dark:border-gray-800';
        wrapper.setAttribute('data-standalone-quota-row', '');
        wrapper.innerHTML = `
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[140px_minmax(0,1fr)_180px_180px_120px]">
                <div class="flex items-end">
                    <label class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        <input type="hidden" name="quotas[${index}][selected]" value="0">
                        <input type="checkbox" name="quotas[${index}][selected]" value="1" checked>
                        Considerar
                    </label>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Competencia / referencia</label>
                    <input type="text" name="quotas[${index}][reference_label]" placeholder="mm/aaaa" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Vencimento</label>
                    <input type="date" name="quotas[${index}][due_date]" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Valor original</label>
                    <input type="text" name="quotas[${index}][original_amount]" placeholder="0,00" class="{{ $inputClass }}" data-decimal-mask>
                </div>
                <div class="flex items-end">
                    <button type="button" class="h-11 w-full rounded-xl border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-remove-standalone-quota>Remover</button>
                </div>
            </div>
        `;
        bindDecimalMasks(wrapper);
        return wrapper;
    }

    function setBreakdownValue(id, value) {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    }

    function renderPreview(data) {
        previewEmpty?.classList.add('hidden');
        previewContent?.classList.remove('hidden');
        if (previewMessage) {
            previewMessage.textContent = `${data.settings.index_label} · ${data.settings.interest_label} · base ${data.settings.final_date}`;
            previewMessage.className = 'mt-1 text-sm text-success-600 dark:text-success-400';
        }

        if (previewDebitTotal) previewDebitTotal.textContent = data.totals.debit_total;
        if (previewAttorneyFee) previewAttorneyFee.textContent = data.totals.attorney_fee;
        if (previewGrandTotal) previewGrandTotal.textContent = data.totals.grand_total;

        setBreakdownValue('preview-total-original', data.totals.original);
        setBreakdownValue('preview-total-corrected', data.totals.corrected);
        setBreakdownValue('preview-total-interest', data.totals.interest);
        setBreakdownValue('preview-total-fine', data.totals.fine);
        setBreakdownValue('preview-total-costs', data.totals.costs_corrected);
        setBreakdownValue('preview-total-boleto-fee', data.totals.boleto_fee);
        setBreakdownValue('preview-total-boleto-cancel', data.totals.boleto_cancellation_fee);
        setBreakdownValue('preview-total-abatement', data.totals.abatement);

        if (previewItems) {
            previewItems.innerHTML = (data.items || []).map((item) => `
                <tr>
                    <td class="px-3 py-3 font-medium text-gray-900 dark:text-white">${escapeHtml(item.reference_label)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.due_date)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.original)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.factor)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.corrected)}</td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.interest)} <span class="text-gray-400">(${escapeHtml(item.interest_percent)})</span></td>
                    <td class="px-3 py-3 text-gray-700 dark:text-gray-200">${escapeHtml(item.fine)}</td>
                    <td class="px-3 py-3 font-semibold text-gray-900 dark:text-white">${escapeHtml(item.total)}</td>
                </tr>
            `).join('');
        }
    }

    async function previewCalculation() {
        if (!previewButton) return;

        previewButton.disabled = true;
        previewButton.textContent = 'Calculando...';
        if (previewMessage) {
            previewMessage.textContent = 'Consultando fatores e calculando a memoria...';
            previewMessage.className = 'mt-1 text-sm text-gray-500 dark:text-gray-400';
        }

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                },
                body: new FormData(form),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Nao foi possivel simular o calculo.');
            }

            renderPreview(data);
        } catch (error) {
            if (previewMessage) {
                previewMessage.textContent = error.message || 'Nao foi possivel simular o calculo.';
                previewMessage.className = 'mt-1 text-sm text-error-600 dark:text-error-400';
            }
        } finally {
            previewButton.disabled = false;
            previewButton.textContent = 'Simular calculo';
        }
    }

    bindDecimalMasks();
    updateConditionalFields();
    fillClientData(false);

    fillClientDataButton?.addEventListener('click', () => fillClientData(true));
    clientSelect?.addEventListener('change', () => fillClientData(false));
    interestType?.addEventListener('change', updateConditionalFields);
    attorneyFeeType?.addEventListener('change', updateConditionalFields);
    previewButton?.addEventListener('click', previewCalculation);

    addQuotaButton?.addEventListener('click', () => {
        quotaContainer.appendChild(createQuotaRow(quotaIndex));
        quotaIndex += 1;
    });

    quotaContainer?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.matches('[data-remove-standalone-quota]')) return;

        const rows = quotaContainer.querySelectorAll('[data-standalone-quota-row]');
        if (rows.length <= 1) {
            const checkbox = rows[0]?.querySelector('input[type="checkbox"]');
            const reference = rows[0]?.querySelector('input[type="text"]');
            const date = rows[0]?.querySelector('input[type="date"]');
            if (checkbox) checkbox.checked = true;
            if (reference) reference.value = '';
            if (date) date.value = '';
            const money = rows[0]?.querySelector('[data-decimal-mask]');
            if (money) money.value = '';
            return;
        }

        target.closest('[data-standalone-quota-row]')?.remove();
    });
})();
</script>
@endpush
@endif
