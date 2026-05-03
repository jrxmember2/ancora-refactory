@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]';
@endphp

@section('content')
<x-ancora.section-header title="Importacao de processos" subtitle="Importe processos em lote com previa antes da gravacao e suporte a fases no mesmo arquivo.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('processos.import.template') }}" class="{{ $softButtonClass }}">Baixar modelo</a>
        <a href="{{ route('processos.create') }}" class="{{ $buttonClass }}">Novo processo</a>
    </div>
</x-ancora.section-header>
@include('pages.processos.partials.subnav')

<div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr,1.35fr]">
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-5 text-sm text-gray-600 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
        <strong class="text-gray-900 dark:text-white">Como funciona</strong>
        <div class="mt-3 space-y-2">
            <p>1. Baixe a planilha de exemplo e preencha uma linha por processo.</p>
            <p>2. Para importar fases, use os blocos <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">phase_1_*</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">phase_2_*</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">phase_3_*</code>. Cada bloco preenchido vira uma fase.</p>
            <p>3. Se precisar de mais fases, repita o mesmo padrao com <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">phase_4_*</code>, <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">phase_5_*</code> e assim por diante.</p>
            <p>4. Envie o CSV para abrir a previa. A criacao so acontece depois da conferencia e do clique em executar.</p>
        </div>

        <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-300">
            <div class="font-semibold text-gray-900 dark:text-white">Campos do processo no modelo</div>
            <div class="mt-2 break-words">
                @foreach($templateHeaders as $header)
                    <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">{{ $header }}</code>@if(!$loop->last), @endif
                @endforeach
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-900/50 dark:text-gray-300">
            <div class="font-semibold text-gray-900 dark:text-white">Campos de fase aceitos</div>
            <div class="mt-2 break-words">
                @foreach($phaseTemplateHeaders as $header)
                    <code class="rounded bg-black/5 px-1 py-0.5 dark:bg-white/10">{{ $header }}</code>@if(!$loop->last), @endif
                @endforeach
            </div>
            <div class="mt-3">Os campos de fases cobrem data, hora, descricao, privacidade, revisao, observacoes, parecer e conferencia.</div>
        </div>

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            O modelo usa ponto e virgula para evitar conflito com valores monetarios em formato brasileiro. Anexos continuam sendo enviados manualmente no processo ou na fase.
        </div>
    </div>

    <form method="post" action="{{ route('processos.import.preview') }}" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]" data-file-preview>
        @csrf
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr,auto] md:items-end">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Arquivo CSV</label>
                <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-3 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                    <i class="fa-solid fa-file-csv"></i>
                    <span>Escolher CSV</span>
                    <input type="file" name="import_file" accept=".csv,text/csv" class="sr-only" data-file-input>
                </label>
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400" data-file-name>Nenhum arquivo selecionado</div>
            </div>
            <button class="{{ $buttonClass }}">Pre-visualizar</button>
        </div>
        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">Aceita CSV separado por virgula ou ponto e virgula. Processos com numero ja existente serao bloqueados na previa.</div>
    </form>
</div>

@if(!empty($importPreview))
    @php
        $previewSummary = $importPreview['summary'] ?? ['total' => 0, 'ready' => 0, 'errors' => 0, 'phases' => 0];
        $actionTypeOptions = $options['action_type'] ?? collect();
        $natureOptions = $options['nature'] ?? collect();
    @endphp
    <dialog id="process-import-preview-modal" class="fixed inset-0 m-auto max-h-[90vh] w-full max-w-6xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900" data-auto-open>
        <div class="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Previa da importacao de processos</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Revise as linhas, os tipos e a quantidade de fases antes de executar.</p>
                </div>
                <button type="button" onclick="document.getElementById('process-import-preview-modal').close()" class="rounded-full border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 px-4 py-3 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500">Linhas</div><div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $previewSummary['total'] ?? 0 }}</div></div>
                <div class="rounded-2xl border border-success-200 bg-success-50 px-4 py-3 dark:border-success-800 dark:bg-success-500/10"><div class="text-xs uppercase tracking-[0.16em] text-success-700 dark:text-success-300">Prontas</div><div class="mt-1 text-xl font-semibold text-success-700 dark:text-success-300">{{ $previewSummary['ready'] ?? 0 }}</div></div>
                <div class="rounded-2xl border border-error-200 bg-error-50 px-4 py-3 dark:border-error-800 dark:bg-error-500/10"><div class="text-xs uppercase tracking-[0.16em] text-error-700 dark:text-error-300">Pendencias</div><div class="mt-1 text-xl font-semibold text-error-700 dark:text-error-300">{{ $previewSummary['errors'] ?? 0 }}</div></div>
                <div class="rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 dark:border-brand-800 dark:bg-brand-500/10"><div class="text-xs uppercase tracking-[0.16em] text-brand-700 dark:text-brand-300">Fases</div><div class="mt-1 text-xl font-semibold text-brand-700 dark:text-brand-300">{{ $previewSummary['phases'] ?? 0 }}</div></div>
            </div>
        </div>

        <form method="post" action="{{ route('processos.import.execute') }}">
            @csrf
            <input type="hidden" name="import_token" value="{{ $importPreviewToken }}">

            <div class="overflow-x-auto px-6 py-5">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs uppercase tracking-[0.16em] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="py-3 pr-4">Linha</th>
                        <th class="py-3 pr-4">Processo</th>
                        <th class="py-3 pr-4">Cliente</th>
                        <th class="py-3 pr-4">Tipo</th>
                        <th class="py-3 pr-4">Fases</th>
                        <th class="py-3 pr-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach(($importPreview['rows'] ?? []) as $row)
                        @php
                            $rowMessages = $row['messages'] ?? [];
                            $rowKey = (string) ($row['row_number'] ?? $loop->iteration);
                            $needsActionTypeResolution = in_array('Tipo de acao nao encontrado nas configuracoes.', $rowMessages, true);
                            $needsNatureResolution = in_array('Natureza nao encontrada nas configuracoes.', $rowMessages, true);
                            $needsClientCondominiumResolution = in_array('Condominio do cliente nao encontrado.', $rowMessages, true);
                            $needsAdverseCondominiumResolution = in_array('Condominio do adverso nao encontrado.', $rowMessages, true);
                            $hasResolvablePendencies = $needsActionTypeResolution || $needsNatureResolution || $needsClientCondominiumResolution || $needsAdverseCondominiumResolution;
                        @endphp
                        <tr>
                            <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">{{ $row['row_number'] ?? '-' }}</td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $row['process_number'] ?: 'Sem numero' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['status_label'] ?? 'Sem status' }}</div>
                            </td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">
                                <div>{{ $row['client_name'] ?: 'Nao informado' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['adverse_name'] ?: 'Adverso nao informado' }}</div>
                            </td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">
                                <div>{{ $row['process_type_label'] ?? 'Nao informado' }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['judging_body'] ?: 'Sem vara/orgao/setor' }}</div>
                            </td>
                            <td class="py-3 pr-4 text-gray-700 dark:text-gray-200">{{ $row['phase_count'] ?? 0 }}</td>
                            <td class="min-w-[320px] py-3 pr-4">
                                @if(($row['preview_status'] ?? '') === 'ready')
                                    <span class="rounded-full bg-success-50 px-3 py-1 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-300">Pronto para criar</span>
                                @else
                                    <div class="rounded-xl border border-error-200 bg-error-50 px-3 py-2 text-xs text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300">{{ implode(' ', $rowMessages ?: ['Pendencia na linha.']) }}</div>

                                    @if($hasResolvablePendencies)
                                        <div class="mt-3 space-y-3 rounded-xl border border-gray-200 bg-white p-3 text-xs dark:border-gray-700 dark:bg-gray-900">
                                            <div class="font-semibold text-gray-900 dark:text-white">Resolver esta linha</div>

                                            @if($needsClientCondominiumResolution)
                                                <div>
                                                    <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Condominio do cliente</label>
                                                    <select name="resolutions[{{ $rowKey }}][client_condominium_mode]" class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                        <option value="">Escolha uma solucao</option>
                                                        <option value="ignore">Cadastrar mesmo assim, sem vincular a condominio</option>
                                                        <option value="select">Indicar condominio existente abaixo</option>
                                                    </select>
                                                    <select name="resolutions[{{ $rowKey }}][client_condominium_id]" class="mt-2 h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                        <option value="">Selecionar condominio existente</option>
                                                        @foreach($condominiums as $condominium)
                                                            <option value="{{ $condominium->id }}">{{ $condominium->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif

                                            @if($needsAdverseCondominiumResolution)
                                                <div>
                                                    <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Condominio do adverso</label>
                                                    <select name="resolutions[{{ $rowKey }}][adverse_condominium_mode]" class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                        <option value="">Escolha uma solucao</option>
                                                        <option value="ignore">Cadastrar mesmo assim, sem vincular a condominio</option>
                                                        <option value="select">Indicar condominio existente abaixo</option>
                                                    </select>
                                                    <select name="resolutions[{{ $rowKey }}][adverse_condominium_id]" class="mt-2 h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                        <option value="">Selecionar condominio existente</option>
                                                        @foreach($condominiums as $condominium)
                                                            <option value="{{ $condominium->id }}">{{ $condominium->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif

                                            @if($needsNatureResolution)
                                                <div>
                                                    <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Natureza</label>
                                                    <select name="resolutions[{{ $rowKey }}][nature_option_id]" class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                        <option value="">Selecionar natureza</option>
                                                        @foreach($natureOptions as $option)
                                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif

                                            @if($needsActionTypeResolution)
                                                <div>
                                                    <label class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Tipo de acao</label>
                                                    <select name="resolutions[{{ $rowKey }}][action_type_option_id]" class="h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                                        <option value="">Selecionar tipo de acao</option>
                                                        @foreach($actionTypeOptions as $option)
                                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-gray-100 px-6 py-5 dark:border-gray-800">
                <button type="button" onclick="document.getElementById('process-import-preview-modal').close()" class="{{ $softButtonClass }}">Voltar e revisar</button>
                <button class="{{ $buttonClass }} disabled:cursor-not-allowed disabled:opacity-50" @disabled(($previewSummary['ready'] ?? 0) === 0 && ($previewSummary['errors'] ?? 0) === 0)>Aplicar correcoes e executar importacao</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-file-input]')) return;
        const wrapper = event.target.closest('[data-file-preview]');
        const label = wrapper?.querySelector('[data-file-name]');
        if (!label) return;
        const files = Array.from(event.target.files || []);
        label.textContent = files.length ? files.map((file) => file.name).join(', ') : 'Nenhum arquivo selecionado';
    });

    document.querySelectorAll('dialog[data-auto-open]').forEach((dialog) => {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    });
</script>
@endpush
