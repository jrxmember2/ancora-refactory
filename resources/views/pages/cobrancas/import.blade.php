@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Importação de inadimplência" subtitle="Suba uma planilha .xls ou .xlsx com Condomínio, Bloco (opcional), Unidade, Referência, Vencimento e Valor.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cobrancas.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Lista de OS</a>
        <a href="{{ route('cobrancas.create') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Nova OS</a>
        <a href="{{ route('cobrancas.import.template') }}" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Baixar modelo</a>
    </div>
</x-ancora.section-header>
@include('pages.cobrancas.partials.subnav')

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Nova importação</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A primeira aba da planilha será lida. O sistema faz a prévia, valida a unidade e só depois processa a criação/atualização das OS.</p>

            <form method="post" action="{{ route('cobrancas.import.preview') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Planilha de inadimplência</label>
                    <input type="file" name="spreadsheet" accept=".xls,.xlsx" class="block w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-500 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white dark:border-gray-700 dark:text-white">
                </div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach(['Condomínio', 'Bloco (opcional)', 'Unidade', 'Referência', 'Vencimento', 'Valor'] as $field)
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">{{ $field }}</div>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Analisar planilha</button>
                    <a href="{{ route('cobrancas.import.template') }}" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Baixar modelo</a>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Bloco vazio, 0 ou - será tratado como sem bloco.</span>
                </div>
            </form>
        </div>

        @if($batch)
            @php
                $emptyProcessedBatch = $emptyProcessedBatch ?? (
                    $batch->status === 'processed'
                    && ((int) $batch->created_cases + (int) $batch->updated_cases + (int) $batch->created_quotas + (int) $batch->duplicate_rows) === 0
                );
            @endphp
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lote #{{ $batch->id }} · {{ $batch->original_name }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Aba: {{ $batch->sheet_name ?: 'Planilha 1' }} · Status: {{ $batch->status === 'processed' ? 'Processado' : 'Aguardando processamento' }}</p>
                    </div>
                    @if(($batch->status !== 'processed' && (int) $batch->ready_rows > 0) || $emptyProcessedBatch)
                        <form method="post" action="{{ route('cobrancas.import.process', $batch) }}">
                            @csrf
                            <button class="rounded-xl bg-success-600 px-4 py-3 text-sm font-medium text-white">{{ $emptyProcessedBatch ? 'Reprocessar lote' : 'Processar lote' }}</button>
                        </form>
                    @elseif($batch->status !== 'processed')
                        <div class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-300">Sem linhas prontas para processar</div>
                    @else
                        <div class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-900/40 dark:bg-success-500/10 dark:text-success-300">Lote processado em {{ optional($batch->processed_at)->format('d/m/Y H:i') }}</div>
                    @endif
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Linhas</div><div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $batch->total_rows }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Prontas</div><div class="mt-2 text-2xl font-semibold text-success-600 dark:text-success-300">{{ $batch->ready_rows }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Pendentes</div><div class="mt-2 text-2xl font-semibold text-warning-600 dark:text-warning-300">{{ $batch->pending_rows }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Duplicidades</div><div class="mt-2 text-2xl font-semibold text-error-600 dark:text-error-300">{{ $batch->duplicate_rows }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">OS criadas</div><div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $batch->created_cases }}</div></div>
                    <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">OS atualizadas</div><div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $batch->updated_cases }}</div></div>
                </div>

                @if($emptyProcessedBatch)
                    <div class="mt-5 rounded-2xl border border-warning-200 bg-warning-50 px-5 py-4 text-sm text-warning-800 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-200">
                        Este lote foi marcado como processado sem gerar alterações. Você pode reprocessá-lo; o sistema vai reanalisar as linhas antes de criar ou atualizar OS.
                    </div>
                @elseif($batch->status !== 'processed' && (int) $batch->ready_rows === 0 && (int) $batch->pending_rows > 0)
                    <div class="mt-5 rounded-2xl border border-warning-200 bg-warning-50 px-5 py-4 text-sm text-warning-800 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-200">
                        Nenhuma linha foi considerada pronta. Confira a coluna <strong>Detalhe</strong>; normalmente isso acontece por condomínio, bloco, unidade, vencimento ou proprietário não encontrado no cadastro.
                    </div>
                @elseif($batch->status !== 'processed' && (int) $batch->pending_rows > 0)
                    <div class="mt-5 rounded-2xl border border-warning-200 bg-warning-50 px-5 py-4 text-sm text-warning-800 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-200">
                        O lote tem linhas pendentes que serão ignoradas no processamento. Revise a coluna <strong>Detalhe</strong> antes de seguir.
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-4">Linha</th>
                                <th class="px-4 py-4">Condomínio / unidade</th>
                                <th class="px-4 py-4">Referência</th>
                                <th class="px-4 py-4">Vencimento</th>
                                <th class="px-4 py-4">Valor</th>
                                <th class="px-4 py-4">Status</th>
                                <th class="px-4 py-4">Detalhe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($rows as $row)
                                <tr>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $row->row_number }}</td>
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $row->condominium_input }}</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row->block_input ?: 'Sem bloco' }} · Unidade {{ $row->unit_input }}</div>
                                        @if($row->unit)
                                            <div class="mt-2 text-xs text-success-600 dark:text-success-300">Vinculado à base: {{ $row->unit->condominium?->name }}{{ $row->unit->block?->name ? ' · '.$row->unit->block->name : '' }} · {{ $row->unit->unit_number }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $row->reference_input }}</td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $row->due_date_input }}</td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">{{ $row->amount_value !== null ? 'R$ '.number_format((float) $row->amount_value, 2, ',', '.') : '—' }}</td>
                                    <td class="px-4 py-4 align-top">
                                        @php
                                            $badge = match($row->status) {
                                                'ready' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
                                                'pending' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
                                                'duplicate' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
                                                'created_case', 'updated_case' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
                                                'error' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
                                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
                                            };
                                            $statusLabel = match($row->status) {
                                                'ready' => 'pronta',
                                                'pending' => 'pendente',
                                                'duplicate' => 'duplicidade',
                                                'created_case' => 'OS criada',
                                                'updated_case' => 'OS atualizada',
                                                'error' => 'erro',
                                                default => str_replace('_', ' ', $row->status),
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $badge }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-sm text-gray-700 dark:text-gray-200">
                                        <div>{{ $row->message ?: '—' }}</div>
                                        @if($row->cobrancaCase)
                                            <div class="mt-2"><a href="{{ route('cobrancas.show', $row->cobrancaCase) }}" class="text-xs font-medium text-brand-600 dark:text-brand-300">Abrir OS {{ $row->cobrancaCase->os_number }}</a></div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8"><x-ancora.empty-state icon="fa-solid fa-file-import" title="Nenhuma linha no lote" subtitle="Suba uma planilha para ver a prévia da importação." /></td></tr>
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
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Regras do importador</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">O botão "Baixar modelo" já entrega uma planilha preenchida como exemplo, pronta para servir de base à importação.</p>
            <ul class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                <li>• Se não existir OS aberta para a unidade, o sistema cria automaticamente uma nova OS em <strong class="text-gray-900 dark:text-white">apto para notificar</strong>.</li>
                <li>• Se já existir OS aberta, a nova referência é adicionada na OS existente.</li>
                <li>• Se a mesma unidade já tiver a mesma referência e vencimento em OS ativa, a linha vira <strong class="text-gray-900 dark:text-white">duplicidade</strong>.</li>
                <li>• O devedor sempre é o <strong class="text-gray-900 dark:text-white">proprietário vinculado à unidade</strong>.</li>
            </ul>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Lotes recentes</h3>
            <div class="mt-4 space-y-3">
                @forelse($recentBatches as $item)
                    <a href="{{ route('cobrancas.import.show', $item) }}" class="block rounded-xl border border-gray-200 p-4 transition hover:border-brand-300 dark:border-gray-800 dark:hover:border-brand-700">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">#{{ $item->id }} · {{ $item->original_name }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($item->created_at)->format('d/m/Y H:i') }} · {{ $item->status === 'processed' ? 'Processado' : 'Prévia' }}</div>
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $item->total_rows }} linhas · {{ $item->ready_rows }} prontas · {{ $item->pending_rows }} pendentes</div>
                    </a>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum lote importado até o momento.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
