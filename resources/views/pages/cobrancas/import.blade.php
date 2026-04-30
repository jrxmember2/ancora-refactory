@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Importacao de inadimplencia" subtitle="Importe a planilha, valide conflitos antes do processamento e concentre as cotas na OS correta sem criar cadastros indevidos.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista de OS</a>
        <a href="{{ route('cobrancas.create') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Nova OS</a>
        <a href="{{ route('cobrancas.import.template') }}" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Baixar modelo</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

@php
    $summary = $summary ?? [
        'total_rows' => 0,
        'ready_rows' => 0,
        'blocking_rows' => 0,
        'ignored_rows' => 0,
        'processed_rows' => 0,
        'counts' => [],
        'processed' => [],
    ];
    $statusOptions = $statusOptions ?? [];
    $statusStyles = $statusStyles ?? [];
    $filters = $filters ?? ['status' => '', 'q' => ''];
@endphp

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Etapa 1 · Upload da planilha</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use o modelo atualizado com condominio, bloco/torre, unidade, proprietario, referencia, vencimento, valor e tipo da cota.</p>

            <form method="post" action="{{ route('cobrancas.import.preview') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Planilha de inadimplencia</label>
                    <input type="file" name="spreadsheet" accept=".xls,.xlsx" class="block w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-500 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white dark:border-gray-700 dark:text-white">
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach(['Condominio', 'CNPJ do condominio (opcional)', 'Bloco/Torre (opcional)', 'Unidade', 'Proprietario', 'Referencia', 'Vencimento', 'Valor', 'Tipo da cota (opcional)'] as $field)
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $field }}</div>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Analisar planilha</button>
                    <a href="{{ route('cobrancas.import.template') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Baixar modelo</a>
                    <span class="text-xs text-gray-500 dark:text-gray-400">A importacao nao cria condominio, unidade nem corrige proprietario automaticamente.</span>
                </div>
            </form>
        </div>

        @if($batch)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lote #{{ $batch->id }} · {{ $batch->original_name }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Status: {{ $batch->status === 'processed' ? 'Processado' : ($batch->status === 'cancelled' ? 'Cancelado' : 'Em conferencia') }} · {{ optional($batch->created_at)->format('d/m/Y H:i') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('cobrancas.import.report', [$batch, 'csv']) }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">CSV</a>
                        <a href="{{ route('cobrancas.import.report', [$batch, 'xlsx']) }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">XLSX</a>
                        <a href="{{ route('cobrancas.import.report', [$batch, 'pdf']) }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">PDF</a>
                        @if($canProcessBatch ?? false)
                            <form method="post" action="{{ route('cobrancas.import.process', $batch) }}">
                                @csrf
                                <button class="rounded-xl bg-success-600 px-4 py-3 text-sm font-medium text-white">Confirmar importacao</button>
                            </form>
                        @elseif(($blockingRowsCount ?? 0) > 0)
                            <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-300">
                                {{ $blockingRowsCount }} linha(s) ainda exigem decisao
                            </div>
                        @endif
                        @if($batch->status !== 'processed' && $batch->status !== 'cancelled')
                            <form method="post" action="{{ route('cobrancas.import.cancel', $batch) }}">
                                @csrf
                                <button class="rounded-xl border border-error-200 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-900/40 dark:text-error-300">Cancelar lote</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if($batch->status !== 'processed' && $batch->status !== 'cancelled')
                    <form id="bulk-import-resolution-form" method="post" action="{{ route('cobrancas.import.resolve.bulk', $batch) }}" class="hidden">
                        @csrf
                        <input type="hidden" name="decisions_json" id="bulk-import-resolution-input">
                    </form>

                    <div id="bulk-import-resolution-panel" class="mt-5 hidden rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 dark:border-brand-900/40 dark:bg-brand-500/10">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <div class="text-xs uppercase tracking-[0.16em] text-brand-700 dark:text-brand-300">Fila de decisoes</div>
                                <div id="bulk-import-resolution-text" class="mt-1 text-sm font-medium text-brand-900 dark:text-brand-100">Nenhuma decisao pendente.</div>
                                <div class="mt-1 text-xs text-brand-700/80 dark:text-brand-200/80">Guarde as decisoes nas linhas e aplique tudo em lote para evitar refresh a cada conflito.</div>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" id="bulk-import-resolution-apply" onclick="submitImportDecisionQueue()" class="rounded-xl bg-brand-600 px-4 py-3 text-sm font-medium text-white">Aplicar decisoes em lote</button>
                                <button type="button" id="bulk-import-resolution-clear" onclick="clearImportDecisionQueue()" class="rounded-xl border border-brand-300 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:text-brand-300">Descartar fila</button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Linhas lidas</div><div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $summary['total_rows'] ?? 0 }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Prontas</div><div class="mt-2 text-2xl font-semibold text-success-600 dark:text-success-300">{{ $summary['ready_rows'] ?? 0 }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Bloqueios</div><div class="mt-2 text-2xl font-semibold text-warning-600 dark:text-warning-300">{{ $summary['blocking_rows'] ?? 0 }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Ignoradas</div><div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $summary['ignored_rows'] ?? 0 }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Processadas</div><div class="mt-2 text-2xl font-semibold text-brand-600 dark:text-brand-300">{{ $summary['processed_rows'] ?? 0 }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Duplicidades</div><div class="mt-2 text-2xl font-semibold text-error-600 dark:text-error-300">{{ ($summary['counts']['warning_duplicate'] ?? 0) + ($summary['counts']['processed_duplicate_skip'] ?? 0) }}</div></div>
                </div>

                @if(($processedSummary['created_cases'] ?? 0) || ($processedSummary['linked_cases'] ?? 0) || ($processedSummary['created_quotas'] ?? 0))
                    <div class="mt-5 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4 text-sm text-brand-800 dark:border-brand-900/40 dark:bg-brand-500/10 dark:text-brand-200">
                        Resultado final: OS criadas {{ $processedSummary['created_cases'] ?? 0 }}, OS reaproveitadas {{ $processedSummary['linked_cases'] ?? 0 }}, cotas incluidas {{ $processedSummary['created_quotas'] ?? 0 }}, duplicidades ignoradas {{ $processedSummary['ignored_duplicates'] ?? 0 }}, erros finais {{ $processedSummary['error_rows'] ?? 0 }}.
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Etapas 3 e 4 · Previa e correcoes</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Resolva apenas o que for permitido. Divergencia de proprietario continua ignorada automaticamente.</p>
                    </div>
                    <form method="get" action="{{ route('cobrancas.import.show', $batch) }}" class="flex flex-wrap gap-3">
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por condominio, unidade, proprietario..." class="w-72 rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                        <select name="status" class="rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                            <option value="">Todos os status</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Filtrar</button>
                    </form>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-4">Linha</th>
                                <th class="px-4 py-4">Condominio / unidade</th>
                                <th class="px-4 py-4">Proprietario</th>
                                <th class="px-4 py-4">Referencia</th>
                                <th class="px-4 py-4">Valor</th>
                                <th class="px-4 py-4">Status</th>
                                <th class="px-4 py-4">Acao</th>
                                <th class="px-4 py-4">Detalhe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($rows as $row)
                                @php
                                    $statusClass = $statusStyles[$row->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
                                    $statusLabel = $statusOptions[$row->status] ?? str_replace('_', ' ', $row->status);
                                    $issue = $row->issue_payload_json ?? [];
                                    $dialogId = 'import-row-dialog-' . $row->id;
                                    $caseOptions = $issue['case_options'] ?? [];
                                    $suggestions = $issue['suggestions'] ?? [];
                                @endphp
                                <tr data-import-row-id="{{ $row->id }}">
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $row->row_number }}</td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $row->condominium_input }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row->block_input ?: 'Sem bloco' }} · Unidade {{ $row->unit_input }}</div>
                                        @if($row->unit)
                                            <div class="mt-2 text-xs text-success-600 dark:text-success-300">
                                                Base: {{ $row->unit->condominium?->name }}{{ $row->unit->block?->name ? ' · '.$row->unit->block->name : '' }} · {{ $row->unit->unit_number }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                        <div>{{ $row->owner_input ?: '—' }}</div>
                                        @if(($issue['owner_from_system'] ?? '') !== '')
                                            <div class="mt-2 text-xs text-error-600 dark:text-error-300">Cadastro: {{ $issue['owner_from_system'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                        <div>{{ $row->reference_input }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row->due_date_input }}</div>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                        <div>{{ $row->amount_value !== null ? 'R$ '.number_format((float) $row->amount_value, 2, ',', '.') : '—' }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row->quota_type_input ?: 'taxa_mes' }}</div>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        @if(in_array($row->status, ['warning_duplicate', 'warning_multi_case', 'error_condominium', 'error_unit', 'error_required'], true))
                                            <button type="button" onclick="openImportDialog('{{ $dialogId }}')" class="rounded-xl border border-brand-200 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:text-brand-300">Corrigir / decidir</button>
                                            <div class="js-import-row-pending mt-2 hidden text-xs font-medium text-brand-600 dark:text-brand-300" data-row-id="{{ $row->id }}"></div>
                                        @elseif($row->cobrancaCase)
                                            <a href="{{ route('cobrancas.show', $row->cobrancaCase) }}" class="text-xs font-medium text-brand-600 dark:text-brand-300">Abrir OS</a>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">Sem acao</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                        <div>{{ $row->message ?: '—' }}</div>
                                    </td>
                                </tr>

                                @if(in_array($row->status, ['warning_duplicate', 'warning_multi_case', 'error_condominium', 'error_unit', 'error_required'], true))
                                    <dialog id="{{ $dialogId }}" class="w-full max-w-4xl rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-gray-900/60 dark:border-gray-800 dark:bg-gray-900">
                                        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Linha {{ $row->row_number }} · {{ $statusLabel }}</h4>
                                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $row->message }}</p>
                                                </div>
                                                <button type="button" onclick="closeImportDialog('{{ $dialogId }}')" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Fechar</button>
                                            </div>
                                        </div>

                                        <div class="space-y-6 px-6 py-6">
                                            <div class="rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800 dark:border-brand-900/40 dark:bg-brand-500/10 dark:text-brand-200">
                                                As decisoes desta linha podem ser guardadas em fila. Voce pode continuar revisando outras linhas e aplicar tudo em lote depois.
                                            </div>
                                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Linha importada</div>
                                                    <div class="mt-3 space-y-2 text-sm text-gray-700 dark:text-gray-200">
                                                        <div><strong>Condominio:</strong> {{ $row->condominium_input }}</div>
                                                        <div><strong>Bloco:</strong> {{ $row->block_input ?: 'Sem bloco' }}</div>
                                                        <div><strong>Unidade:</strong> {{ $row->unit_input }}</div>
                                                        <div><strong>Proprietario:</strong> {{ $row->owner_input }}</div>
                                                        <div><strong>Referencia:</strong> {{ $row->reference_input }}</div>
                                                        <div><strong>Vencimento:</strong> {{ $row->due_date_input }}</div>
                                                        <div><strong>Valor:</strong> {{ $row->amount_value !== null ? 'R$ '.number_format((float) $row->amount_value, 2, ',', '.') : '—' }}</div>
                                                    </div>
                                                </div>

                                                @if($caseOptions !== [])
                                                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">OS relacionadas</div>
                                                        <div class="mt-3 space-y-3">
                                                            @foreach($caseOptions as $caseOption)
                                                                <div class="rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
                                                                    <div class="font-medium text-gray-900 dark:text-white">{{ $caseOption['os_number'] }}</div>
                                                                    <div class="mt-2 text-gray-600 dark:text-gray-300">Status: {{ $caseOption['status'] }} · Situação: {{ $caseOption['situation'] }}</div>
                                                                    <div class="mt-1 text-gray-600 dark:text-gray-300">Abertura: {{ $caseOption['opened_at'] ?: '—' }} · Responsável: {{ $caseOption['responsible'] ?: '—' }}</div>
                                                                    <div class="mt-1 text-gray-600 dark:text-gray-300">Cotas atuais: {{ $caseOption['quotas_count'] }} · Total em aberto: R$ {{ number_format((float) ($caseOption['open_amount'] ?? 0), 2, ',', '.') }}</div>
                                                                    <div class="mt-1 text-gray-600 dark:text-gray-300">Última movimentação: {{ $caseOption['last_progress_at'] ?: '—' }}</div>
                                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                                        @if($row->status === 'warning_multi_case')
                                                                            <form method="post" action="{{ route('cobrancas.import.resolve', [$batch, $row]) }}" class="js-queue-import-decision" data-row-id="{{ $row->id }}">
                                                                                @csrf
                                                                                <input type="hidden" name="action_type" value="use_case">
                                                                                <input type="hidden" name="target_case_id" value="{{ $caseOption['id'] }}">
                                                                                <button class="rounded-xl bg-brand-500 px-3 py-2 text-xs font-medium text-white">Adicionar vinculo a fila</button>
                                                                            </form>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            @if($suggestions !== [])
                                                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Sugestoes encontradas</div>
                                                    <div class="mt-3 space-y-3">
                                                        @foreach($suggestions as $suggestion)
                                                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-800">
                                                                <div>
                                                                    <div class="font-medium text-gray-900 dark:text-white">{{ $suggestion['label'] ?? $suggestion['name'] }}</div>
                                                                    @if(($suggestion['owner_name'] ?? '') !== '')
                                                                        <div class="mt-1 text-gray-500 dark:text-gray-400">Proprietario: {{ $suggestion['owner_name'] }}</div>
                                                                    @endif
                                                                    @if(($suggestion['cnpj'] ?? '') !== '')
                                                                        <div class="mt-1 text-gray-500 dark:text-gray-400">CNPJ: {{ $suggestion['cnpj'] }}</div>
                                                                    @endif
                                                                </div>
                                                                <div class="flex flex-wrap gap-2">
                                                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">Similaridade {{ $suggestion['score'] ?? 0 }}%</span>
                                                                    <form method="post" action="{{ route('cobrancas.import.resolve', [$batch, $row]) }}" class="js-queue-import-decision" data-row-id="{{ $row->id }}">
                                                                        @csrf
                                                                        <input type="hidden" name="action_type" value="correct">
                                                                        <input type="hidden" name="condominium_input" value="{{ $suggestion['condominium_name'] ?? $suggestion['name'] ?? $row->condominium_input }}">
                                                                        <input type="hidden" name="block_input" value="{{ $suggestion['block_name'] ?? $row->block_input }}">
                                                                        <input type="hidden" name="unit_input" value="{{ $suggestion['unit_number'] ?? $row->unit_input }}">
                                                                        <input type="hidden" name="owner_input" value="{{ $row->owner_input }}">
                                                                        <input type="hidden" name="reference_input" value="{{ $row->reference_input }}">
                                                                        <input type="hidden" name="due_date_input" value="{{ $row->due_date_input }}">
                                                                        <input type="hidden" name="amount_value" value="{{ $row->amount_value }}">
                                                                        <input type="hidden" name="quota_type_input" value="{{ $row->quota_type_input }}">
                                                                        <button class="rounded-xl border border-brand-200 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:text-brand-300">Adicionar sugestao a fila</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            @if(in_array($row->status, ['warning_duplicate', 'warning_multi_case'], true))
                                                <div class="flex flex-wrap gap-3">
                                                    <form method="post" action="{{ route('cobrancas.import.resolve', [$batch, $row]) }}" class="js-queue-import-decision" data-row-id="{{ $row->id }}">
                                                        @csrf
                                                        <input type="hidden" name="action_type" value="create_new_case">
                                                        <button class="rounded-xl bg-success-600 px-4 py-3 text-sm font-medium text-white">Adicionar nova OS a fila</button>
                                                    </form>
                                                    <form method="post" action="{{ route('cobrancas.import.resolve', [$batch, $row]) }}" class="js-queue-import-decision" data-row-id="{{ $row->id }}">
                                                        @csrf
                                                        <input type="hidden" name="action_type" value="ignore_line">
                                                        <button class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Adicionar ignorar a fila</button>
                                                    </form>
                                                    <form method="post" action="{{ route('cobrancas.import.resolve', [$batch, $row]) }}">
                                                        @csrf
                                                        <input type="hidden" name="action_type" value="cancel_import">
                                                        <button class="rounded-xl border border-error-200 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-900/40 dark:text-error-300">Cancelar importacao</button>
                                                    </form>
                                                </div>
                                            @endif

                                            @if(in_array($row->status, ['error_condominium', 'error_unit', 'error_required'], true))
                                                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Correcao manual</div>
                                                    <form method="post" action="{{ route('cobrancas.import.resolve', [$batch, $row]) }}" class="js-queue-import-decision mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" data-row-id="{{ $row->id }}">
                                                        @csrf
                                                        <input type="hidden" name="action_type" value="correct">
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Condominio</label>
                                                            <input type="text" name="condominium_input" value="{{ $row->condominium_input }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Bloco/Torre</label>
                                                            <input type="text" name="block_input" value="{{ $row->block_input }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Unidade</label>
                                                            <input type="text" name="unit_input" value="{{ $row->unit_input }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Proprietario</label>
                                                            <input type="text" name="owner_input" value="{{ $row->owner_input }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Referencia</label>
                                                            <input type="text" name="reference_input" value="{{ $row->reference_input }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Vencimento</label>
                                                            <input type="text" name="due_date_input" value="{{ $row->due_date_input }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div>
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor</label>
                                                            <input type="text" name="amount_value" value="{{ $row->amount_value }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo da cota</label>
                                                            <input type="text" name="quota_type_input" value="{{ $row->quota_type_input }}" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                                                        </div>
                                                        <div class="md:col-span-2 flex flex-wrap gap-3">
                                                            <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Adicionar correcao a fila</button>
                                                        </div>
                                                    </form>
                                                    <form method="post" action="{{ route('cobrancas.import.resolve', [$batch, $row]) }}" class="js-queue-import-decision mt-3" data-row-id="{{ $row->id }}">
                                                        @csrf
                                                        <input type="hidden" name="action_type" value="ignore_line">
                                                        <button class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Adicionar ignorar a fila</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </dialog>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8">
                                        <x-ancora.empty-state icon="fa-solid fa-file-import" title="Nenhuma linha encontrada" subtitle="Envie uma planilha para iniciar a conferencia da importacao." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($rows)
                    <div class="border-t border-gray-100 px-4 py-4 dark:border-gray-800">{{ $rows->links() }}</div>
                @endif
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Regras operacionais</h3>
            <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <li>• O importador nunca cria condominio automaticamente.</li>
                <li>• O importador nunca cria unidade automaticamente.</li>
                <li>• Divergencia de proprietario ignora a linha sem abrir correcao.</li>
                <li>• Se existir uma OS reutilizavel para a mesma unidade, a cota entra nela.</li>
                <li>• Se houver cota duplicada exata, o usuario decide criar nova OS ou ignorar.</li>
                <li>• Um erro isolado nao derruba o lote inteiro.</li>
            </ul>
        </div>

        @if($batch)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo por status</h3>
                <div class="mt-4 space-y-3">
                    @foreach($statusOptions as $statusKey => $statusLabel)
                        @php
                            $count = (int) ($summary['counts'][$statusKey] ?? 0);
                            $statusClass = $statusStyles[$statusKey] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
                        @endphp
                        @if($count > 0)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $count }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lotes recentes</h3>
            <div class="mt-4 space-y-3">
                @forelse($recentBatches as $item)
                    <a href="{{ route('cobrancas.import.show', $item) }}" class="block rounded-xl border border-gray-200 p-4 transition hover:border-brand-300 dark:border-gray-800 dark:hover:border-brand-700">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">#{{ $item->id }} · {{ $item->original_name }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($item->created_at)->format('d/m/Y H:i') }} · {{ $item->status === 'processed' ? 'Processado' : ($item->status === 'cancelled' ? 'Cancelado' : 'Em conferencia') }}</div>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item->total_rows }} linhas · {{ $item->ready_rows }} prontas · {{ $item->pending_rows }} com bloqueio</div>
                    </a>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum lote importado ate o momento.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
    const importBatchId = @json($batch?->id);
    const importDecisionQueueShouldClear = @json((bool) session('importDecisionQueueCleared'));
    let importDecisionQueue = {};

    function importDecisionQueueKey() {
        return importBatchId ? `cobrancas-import-queue-${importBatchId}` : null;
    }

    function loadImportDecisionQueue() {
        const key = importDecisionQueueKey();
        if (!key) {
            importDecisionQueue = {};
            return;
        }

        if (importDecisionQueueShouldClear) {
            sessionStorage.removeItem(key);
            importDecisionQueue = {};
            return;
        }

        try {
            const raw = sessionStorage.getItem(key);
            const list = raw ? JSON.parse(raw) : [];
            importDecisionQueue = {};

            if (Array.isArray(list)) {
                list.forEach((item) => {
                    const rowId = Number(item?.row_id || 0);
                    if (rowId > 0) {
                        importDecisionQueue[rowId] = item;
                    }
                });
            }
        } catch (error) {
            importDecisionQueue = {};
        }
    }

    function saveImportDecisionQueue() {
        const key = importDecisionQueueKey();
        if (!key) {
            return;
        }

        sessionStorage.setItem(key, JSON.stringify(Object.values(importDecisionQueue)));
        refreshImportDecisionQueueUI();
    }

    function describeImportDecision(decision) {
        const actionType = String(decision?.action_type || '');

        if (actionType === 'ignore_line') {
            return 'Ignorar linha';
        }
        if (actionType === 'create_new_case') {
            return 'Criar nova OS';
        }
        if (actionType === 'use_case') {
            return `Vincular na OS #${decision?.target_case_id || '?'}`;
        }
        if (actionType === 'correct') {
            return 'Correcao pendente';
        }

        return 'Decisao pendente';
    }

    function refreshImportDecisionQueueUI() {
        const panel = document.getElementById('bulk-import-resolution-panel');
        const text = document.getElementById('bulk-import-resolution-text');
        const applyButton = document.getElementById('bulk-import-resolution-apply');
        const clearButton = document.getElementById('bulk-import-resolution-clear');
        const decisions = Object.values(importDecisionQueue);
        const total = decisions.length;

        if (panel) {
            panel.classList.toggle('hidden', total === 0);
        }
        if (text) {
            text.textContent = total === 1
                ? '1 linha com decisao pendente para aplicar em lote.'
                : `${total} linha(s) com decisao pendente para aplicar em lote.`;
        }
        if (applyButton) {
            applyButton.disabled = total === 0;
            applyButton.classList.toggle('opacity-50', total === 0);
            applyButton.classList.toggle('cursor-not-allowed', total === 0);
        }
        if (clearButton) {
            clearButton.disabled = total === 0;
        }

        document.querySelectorAll('.js-import-row-pending').forEach((element) => {
            const rowId = Number(element.dataset.rowId || 0);
            const decision = importDecisionQueue[rowId];

            if (decision) {
                element.textContent = describeImportDecision(decision);
                element.classList.remove('hidden');
            } else {
                element.textContent = '';
                element.classList.add('hidden');
            }
        });
    }

    function queueImportDecision(form) {
        if (!(form instanceof HTMLFormElement)) {
            return true;
        }

        const rowId = Number(form.dataset.rowId || 0);
        const formData = new FormData(form);
        const actionType = String(formData.get('action_type') || '').trim();

        if (rowId <= 0 || actionType === '' || actionType === 'cancel_import') {
            return true;
        }

        const payload = { row_id: rowId };
        formData.forEach((value, key) => {
            payload[key] = typeof value === 'string' ? value.trim() : value;
        });

        importDecisionQueue[rowId] = payload;
        saveImportDecisionQueue();

        const dialog = form.closest('dialog');
        if (dialog instanceof HTMLDialogElement) {
            dialog.close();
        }

        return false;
    }

    function clearImportDecisionQueue() {
        const key = importDecisionQueueKey();
        if (key) {
            sessionStorage.removeItem(key);
        }
        importDecisionQueue = {};
        refreshImportDecisionQueueUI();
    }

    function submitImportDecisionQueue() {
        const form = document.getElementById('bulk-import-resolution-form');
        const input = document.getElementById('bulk-import-resolution-input');
        const decisions = Object.values(importDecisionQueue);

        if (!form || !input || decisions.length === 0) {
            return;
        }

        input.value = JSON.stringify(decisions);
        form.submit();
    }

    function openImportDialog(id) {
        const dialog = document.getElementById(id);
        if (dialog) dialog.showModal();
    }

    function closeImportDialog(id) {
        const dialog = document.getElementById(id);
        if (dialog) dialog.close();
    }

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (target instanceof HTMLDialogElement) {
            target.close();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        loadImportDecisionQueue();
        refreshImportDecisionQueueUI();

        document.querySelectorAll('.js-queue-import-decision').forEach((form) => {
            form.addEventListener('submit', function (event) {
                if (!queueImportDecision(form)) {
                    event.preventDefault();
                }
            });
        });
    });
</script>
@endsection
