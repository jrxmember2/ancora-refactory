@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]';
    $money = fn ($value) => $value !== null && $value !== '' ? 'R$ ' . number_format((float) $value, 2, ',', '.') : 'Nao informado';
    $date = fn ($value) => $value ? $value->format('d/m/Y') : 'Nao informado';
@endphp

@section('content')
<x-ancora.section-header :title="$case->process_number ?: 'Processo #' . $case->id" subtitle="Resumo, fases processuais e anexos do caso.">
    <div class="flex flex-wrap gap-3">
        @if($case->process_number && $case->datajud_court)
            <form method="post" action="{{ route('processos.datajud.sync', $case) }}">
                @csrf
                <button class="{{ $softButtonClass }}">Sincronizar DataJud</button>
            </form>
        @endif
        <a href="{{ route('processos.edit', $case) }}" class="{{ $softButtonClass }}">Editar</a>
        <a href="{{ route('processos.index') }}" class="{{ $softButtonClass }}">Voltar</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Cliente</div>
        <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $case->client_name_snapshot ?: 'Nao informado' }}</div>
        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $case->clientPositionOption?->name ?: 'Posicao nao informada' }}</div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Adverso</div>
        <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $case->adverse_name ?: 'Nao informado' }}</div>
        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $case->adversePositionOption?->name ?: 'Posicao nao informada' }}</div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Status</div>
        @php
            $statusColor = $case->statusOption?->color_hex ?: '#6B7280';
        @endphp
        <div class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-semibold text-white" style="background-color: {{ $statusColor }}">{{ $case->statusOption?->name ?: 'Sem status' }}</div>
        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $case->processTypeOption?->name ?: 'Tipo nao informado' }}</div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">DataJud</div>
        <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $case->datajud_court ?: 'Nao configurado' }}</div>
        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ultima busca: {{ $case->last_datajud_sync_at?->format('d/m/Y H:i') ?: 'nunca' }}</div>
    </div>
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-2 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-wrap gap-2">
        @foreach(['resumo' => 'Resumo', 'fases' => 'Fases', 'anexos' => 'Anexos'] as $tab => $label)
            <a href="{{ route('processos.show', ['processo' => $case, 'tab' => $tab]) }}" class="rounded-xl px-4 py-2 text-sm font-medium {{ $activeTab === $tab ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.05]' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

@if($activeTab === 'resumo')
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>
                @php
                    $judgingBodyLabel = match (\Illuminate\Support\Str::slug((string) ($case->processTypeOption?->name ?? ''))) {
                        'administrativo' => 'Orgao/Setor',
                        'judicial' => 'Vara/Setor',
                        default => 'Vara/Orgao/Setor',
                    };

                    $mainDetails = [
                        'Responsavel' => $case->responsible_lawyer ?: 'Nao informado',
                        'Abertura' => $date($case->opened_at),
                        'Tipo de acao' => $case->actionTypeOption?->name ?: 'Nao informado',
                        'Natureza' => $case->natureOption?->name ?: 'Nao informado',
                        'Advogado do cliente' => $case->client_lawyer ?: 'Nao informado',
                        'Advogado do adverso' => $case->adverse_lawyer ?: 'Nao informado',
                    ];

                    if (filled($case->judging_body)) {
                        $mainDetails[$judgingBodyLabel] = $case->judging_body;
                    }
                @endphp
                <dl class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($mainDetails as $label => $value)
                        <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                            <dt class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-4 rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Observacoes</div>
                    <div class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $case->notes ?: 'Nenhuma observacao cadastrada.' }}</div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Encerramento</h3>
                <dl class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                        <dt class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Data</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $date($case->closed_at) }}</dd>
                    </div>
                    <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                        <dt class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Responsavel</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $case->closed_by ?: 'Nao informado' }}</dd>
                    </div>
                    <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                        <dt class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Tipo</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $case->closureTypeOption?->name ?: 'Nao informado' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 whitespace-pre-line rounded-2xl border border-gray-100 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">{{ $case->closure_notes ?: 'Sem observacoes de encerramento.' }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Valores</h3>
            <div class="mt-5 space-y-3">
                @foreach([
                    ['Valor da causa', $money($case->claim_amount), $date($case->claim_amount_date)],
                    ['Valor provisionado', $money($case->provisioned_amount), $date($case->provisioned_amount_date)],
                    ['Total pago em juizo', $money($case->court_paid_amount), $date($case->court_paid_amount_date)],
                    ['Custo do processo', $money($case->process_cost_amount), $date($case->process_cost_amount_date)],
                    ['Valor da sentenca', $money($case->sentence_amount), $date($case->sentence_amount_date)],
                ] as [$label, $value, $when])
                    <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $value }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Data: {{ $when }}</div>
                    </div>
                @endforeach
                <div class="rounded-2xl border border-brand-100 bg-brand-50 p-4 dark:border-brand-900/60 dark:bg-brand-500/10">
                    <div class="text-xs uppercase tracking-[0.14em] text-brand-600 dark:text-brand-300">Possibilidade de ganho</div>
                    <div class="mt-1 text-sm font-semibold text-brand-900 dark:text-brand-100">{{ $case->winProbabilityOption?->name ?: 'Nao informada' }}</div>
                </div>
            </div>
        </div>
    </div>
@elseif($activeTab === 'fases')
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Fases e andamentos</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Movimentos manuais e registros trazidos pelo DataJud.</p>
            </div>
            <button type="button" onclick="document.getElementById('phase-modal').showModal()" class="{{ $buttonClass }}">Cadastrar fase</button>
        </div>

        <div class="mt-6 space-y-4">
            @forelse($case->phases as $phase)
                <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $phase->description }}</span>
                                @if($phase->source === 'datajud')
                                    <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">DataJud</span>
                                @endif
                                @if($phase->is_private)
                                    <span class="rounded-full bg-error-50 px-2 py-0.5 text-xs font-medium text-error-700 dark:bg-error-500/10 dark:text-error-300">Privado</span>
                                @endif
                                @if($phase->is_reviewed)
                                    <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-300">Parecer revisado</span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $phase->phase_date?->format('d/m/Y') ?: 'Sem data' }}{{ $phase->phase_time ? ' as ' . substr((string) $phase->phase_time, 0, 5) : '' }}
                                @if($phase->creator) &middot; {{ $phase->creator->email }} @endif
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $phase->attachments->count() }} anexo(s)</div>
                    </div>
                    @if($phase->notes)
                        <div class="mt-4 rounded-2xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/40">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ $phase->source === 'datajud' ? 'Detalhes DataJud' : 'Observacoes' }}</div>
                            <div class="whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $phase->notes }}</div>
                        </div>
                    @endif
                    @if($phase->source === 'datajud' && collect(data_get($phase->datajud_payload_json, 'anexos', []))->isNotEmpty())
                        <div class="mt-4 rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Anexos/localizadores DataJud</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach(data_get($phase->datajud_payload_json, 'anexos', []) as $attachment)
                                    @if(!empty($attachment['url']))
                                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-xl border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">
                                            {{ $attachment['label'] ?? 'Documento' }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($phase->legal_opinion || $phase->conference)
                        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                                <div class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Parecer</div>
                                <div class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $phase->legal_opinion ?: 'Nao informado' }}</div>
                            </div>
                            <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                                <div class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Conferencia</div>
                                <div class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-200">{{ $phase->conference ?: 'Nao informada' }}</div>
                            </div>
                        </div>
                    @endif
                    @if($phase->attachments->count())
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($phase->attachments as $attachment)
                                <a href="{{ route('processos.attachments.download', [$case, $attachment]) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">{{ $attachment->original_name }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-list-check" title="Nenhuma fase cadastrada" subtitle="Cadastre manualmente ou sincronize pelo DataJud quando houver numero de processo e tribunal." />
            @endforelse
        </div>
    </div>

    <dialog id="phase-modal" class="fixed inset-0 m-auto max-h-[92vh] w-full max-w-4xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
        <form method="post" action="{{ route('processos.phases.store', $case) }}" enctype="multipart/form-data" class="p-6">
            @csrf
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cadastrar fase</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Registre o andamento e, se necessario, anexe arquivos.</p>
                </div>
                <button type="button" onclick="document.getElementById('phase-modal').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
            </div>
            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data</label>
                    <input type="date" name="phase_date" value="{{ now()->format('Y-m-d') }}" class="{{ $inputClass }}" required>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Hora</label>
                    <input type="time" name="phase_time" value="{{ now()->format('H:i') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Arquivo</label>
                    <input type="file" name="files[]" multiple class="{{ $inputClass }} file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700">
                </div>
                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Descricao</label>
                    <input name="description" class="{{ $inputClass }}" required>
                </div>
                <div class="md:col-span-3 flex flex-wrap gap-4 text-sm text-gray-700 dark:text-gray-300">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_private" value="1">
                        Privado
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-500 dark:bg-gray-800" title="Andamentos privados nao serao exibidos futuramente no Portal do Cliente.">?</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_reviewed" value="1">
                        Parecer revisado
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-xs text-gray-500 dark:bg-gray-800" title="Indica que o texto do parecer ja foi revisado antes de eventual compartilhamento.">?</span>
                    </label>
                </div>
                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacoes</label>
                    <textarea name="notes" rows="3" class="{{ $textareaClass }}"></textarea>
                </div>
                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Parecer</label>
                    <textarea name="legal_opinion" rows="4" class="{{ $textareaClass }}"></textarea>
                </div>
                <div class="md:col-span-3">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Conferencia</label>
                    <textarea name="conference" rows="4" class="{{ $textareaClass }}"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('phase-modal').close()" class="{{ $softButtonClass }}">Cancelar</button>
                <button class="{{ $buttonClass }}">Salvar</button>
            </div>
        </form>
    </dialog>
@else
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Adicionar anexo</h3>
            <form method="post" action="{{ route('processos.attachments.upload', $case) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <input name="file_role" value="documento" class="{{ $inputClass }}" placeholder="Tipo do arquivo">
                <input type="file" name="files[]" multiple class="{{ $inputClass }} file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700">
                <button class="{{ $buttonClass }} w-full">Anexar</button>
            </form>
        </div>
        <div class="lg:col-span-2 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            @if($case->attachments->count() === 0)
                <div class="p-6">
                    <x-ancora.empty-state icon="fa-solid fa-paperclip" title="Nenhum anexo" subtitle="Adicione documentos, pecas e comprovantes do processo." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left">
                        <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/40">
                            <tr class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                <th class="px-6 py-4">Arquivo</th>
                                <th class="px-6 py-4">Tipo</th>
                                <th class="px-6 py-4">Enviado em</th>
                                <th class="px-6 py-4 text-right">Acoes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($case->attachments as $attachment)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $attachment->original_name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $attachment->file_role }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">{{ $attachment->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('processos.attachments.download', [$case, $attachment]) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Baixar</a>
                                            <form method="post" action="{{ route('processos.attachments.delete', [$case, $attachment]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button onclick="return confirm('Excluir este anexo?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    @if($openPhase)
        document.getElementById('phase-modal')?.showModal();
    @endif
});
</script>
@endpush
