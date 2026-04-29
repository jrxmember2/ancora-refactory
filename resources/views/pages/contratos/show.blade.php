@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$item->code ?: $item->title" :subtitle="$item->title">
    <div class="flex flex-wrap gap-3">
        @if($signatureStorageReady ?? false)
            <a href="{{ route('contratos.signatures.create', $item) }}" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Assinatura digital</a>
        @else
            <span class="rounded-xl border border-gray-200 bg-gray-100 px-4 py-3 text-sm font-medium text-gray-400 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-500">Assinatura digital</span>
        @endif
        <button type="button" onclick="document.getElementById('contract-pdf-modal').showModal()" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Gerar PDF</button>
        @if($item->final_pdf_path)
            <a href="{{ route('contratos.download-pdf', $item) }}" class="rounded-xl border border-success-300 bg-success-50 px-4 py-3 text-sm font-medium text-success-700 dark:border-success-800 dark:bg-success-500/10 dark:text-success-200">Baixar PDF</a>
        @endif
        <a href="{{ route('contratos.edit', $item) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Editar</a>
        <a href="{{ route('contratos.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
    </div>
</x-ancora.section-header>

@if(!empty($contractAlerts))
    <div class="space-y-3">
        @foreach($contractAlerts as $alert)
            <div class="rounded-2xl border px-5 py-4 text-sm {{ ($alert['type'] ?? '') === 'warning' ? 'border-warning-300 bg-warning-50 text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200' : 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200' }}">
                <div class="font-semibold">{{ $alert['label'] ?? 'Alerta' }}</div>
                <div class="mt-1">{{ $alert['message'] ?? '' }}</div>
            </div>
        @endforeach
    </div>
@endif

<dialog id="contract-pdf-modal" class="fixed inset-0 m-auto w-full max-w-2xl rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
    <form method="post" action="{{ route('contratos.generate-pdf', $item) }}" class="p-6">
        @csrf
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Gerar PDF do contrato</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Selecione quais documentos do cadastro do contratante devem entrar como anexos ao final do PDF.</p>
            </div>
            <button type="button" onclick="document.getElementById('contract-pdf-modal').close()" class="rounded-full border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Fechar</button>
        </div>

        <div class="mt-5">
            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacao da versao</label>
            <input name="version_notes" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="Ex.: ajuste final, versao para assinatura, contrato consolidado...">
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
            <button type="button" onclick="document.getElementById('contract-pdf-modal').close()" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Cancelar</button>
            <button class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Gerar PDF</button>
        </div>
    </form>
</dialog>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Cliente</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $item->client?->display_name ?: 'Nao informado' }}</div></div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Condominio</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $item->condominium?->name ?: 'Nao aplicavel' }}</div></div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Status</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $statusLabels[$item->status] ?? $item->status }}</div></div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Valor</div><div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">R$ {{ number_format((float) ($item->contract_value ?? $item->monthly_value ?? $item->total_value ?? 0), 2, ',', '.') }}</div></div>
</div>

<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-2 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-wrap gap-2">
        @foreach(['resumo' => 'Resumo', 'assinaturas' => 'Assinaturas', 'historico' => 'Historico', 'anexos' => 'Anexos'] as $tab => $label)
            <a href="{{ route('contratos.show', ['contrato' => $item, 'tab' => $tab]) }}" class="rounded-xl px-4 py-2 text-sm font-medium {{ $activeTab === $tab ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/[0.05]' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>

@if($activeTab === 'resumo')
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados principais</h3>
                <dl class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach([
                        'Tipo' => $item->type,
                        'Categoria' => $item->category?->name ?: 'Nao informada',
                        'Template' => $item->template?->name ?: 'Nao informado',
                        'Sindico' => $item->syndic?->display_name ?: ($item->condominium?->syndic?->display_name ?: 'Nao informado'),
                        'Responsavel' => $item->responsible?->name ?: 'Nao informado',
                        'Inicio' => optional($item->start_date)->format('d/m/Y') ?: 'Nao informado',
                        'Termino' => $item->indefinite_term ? 'Prazo indeterminado' : (optional($item->end_date)->format('d/m/Y') ?: 'Nao informado'),
                        'Proximo reajuste' => optional($item->next_adjustment_date)->format('d/m/Y') ?: 'Nao informado',
                        'Recorrencia' => $item->recurrence ?: 'Nao informada',
                        'Reajuste' => $item->adjustment_index ?: 'Nao informado',
                    ] as $label => $value)
                        <div class="rounded-2xl border border-gray-100 p-4 dark:border-gray-800">
                            <dt class="text-xs uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Corpo do contrato</h3>
                <div class="prose prose-sm mt-5 max-w-none rounded-2xl border border-gray-100 p-5 dark:prose-invert dark:border-gray-800">{!! $item->content_html ?: '<p>Sem conteudo salvo.</p>' !!}</div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Financeiro 360</h3>
                <div class="mt-4 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                    <div><span class="text-gray-500">Forma de cobranca:</span> {{ $item->billing_type ?: 'Nao informada' }}</div>
                    <div><span class="text-gray-500">Conta financeira:</span> {{ $item->financialAccount?->name ?: 'Nao informada' }}</div>
                    <div><span class="text-gray-500">Forma de pagamento:</span> {{ $paymentMethodLabels[$item->payment_method] ?? ($item->payment_method ?: 'Nao informada') }}</div>
                    <div><span class="text-gray-500">Dia de vencimento:</span> {{ $item->due_day ?: 'Nao informado' }}</div>
                    <div><span class="text-gray-500">Gera cobranca automatica:</span> {{ $item->generate_financial_entries ? 'Sim' : 'Nao' }}</div>
                    <div><span class="text-gray-500">Centro de custo futuro:</span> {{ $item->cost_center_future ?: 'Nao informado' }}</div>
                    <div><span class="text-gray-500">Categoria financeira futura:</span> {{ $item->financial_category_future ?: 'Nao informada' }}</div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Acoes rapidas</h3>
                <div class="mt-4 flex flex-col gap-3">
                    <form method="post" action="{{ route('contratos.duplicate', $item) }}">@csrf<button class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Duplicar contrato</button></form>
                    <form method="post" action="{{ route('contratos.archive', $item) }}">@csrf<button class="w-full rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-700 dark:border-warning-800 dark:bg-warning-500/10 dark:text-warning-200">Arquivar</button></form>
                    <form method="post" action="{{ route('contratos.rescind', $item) }}">@csrf<button class="w-full rounded-xl border border-error-300 bg-error-50 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-200">Rescindir</button></form>
                    <form method="post" action="{{ route('contratos.delete', $item) }}">@csrf @method('DELETE')<button onclick="return confirm('Mover este contrato para a lixeira?')" class="w-full rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-800 dark:text-error-300">Mover para lixeira</button></form>
                </div>
            </div>
        </div>
    </div>
@elseif($activeTab === 'assinaturas')
    <div class="mt-6">
        @if($signatureStorageReady ?? false)
            @include('pages.signatures.panel', [
                'requests' => $item->signatureRequests,
                'createUrl' => route('contratos.signatures.create', $item),
                'routePrefix' => 'contratos.signatures',
                'ownerRouteParam' => 'contrato',
                'owner' => $item,
            ])
        @else
            <div class="rounded-2xl border border-warning-300 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-800/60 dark:bg-warning-500/10 dark:text-warning-200">
                Rode a migration da assinatura digital para liberar este painel no modulo de contratos.
            </div>
        @endif
    </div>
@elseif($activeTab === 'historico')
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Historico de versoes</h3>
        <div class="mt-5 space-y-4">
            @forelse($item->versions as $version)
                <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white">Versao {{ $version->version_number }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ optional($version->generated_at)->format('d/m/Y H:i') ?: 'Data nao informada' }} · {{ $version->generator?->name ?: 'Sistema' }}</div>
                            @if($version->notes)
                                <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $version->notes }}</div>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('contratos.versions.view', [$item, $version]) }}" target="_blank" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200">Visualizar</a>
                            <a href="{{ route('contratos.versions.download', [$item, $version]) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">Baixar PDF</a>
                        </div>
                    </div>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-clock-rotate-left" title="Sem versoes geradas" subtitle="Gere o primeiro PDF do contrato para iniciar o historico." />
            @endforelse
        </div>
    </div>
@else
    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Adicionar anexo</h3>
            <form method="post" action="{{ route('contratos.attachments.upload', $item) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <select name="file_type" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="outro">Outro</option>
                    <option value="contrato_assinado">Contrato assinado</option>
                    <option value="documento_pessoal">Documento pessoal</option>
                    <option value="ata_eleicao">ATA de eleicao</option>
                    <option value="procuracao">Procuracao</option>
                    <option value="comprovante">Comprovante</option>
                </select>
                <input name="description" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" placeholder="Descricao do anexo">
                <input type="file" name="files[]" multiple class="w-full rounded-xl border border-dashed border-gray-300 px-4 py-4 text-sm dark:border-gray-700">
                <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Enviar anexo</button>
            </form>
        </div>
        <div class="xl:col-span-2 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos</h3>
            <div class="mt-5 space-y-3">
                @forelse($item->attachments as $attachment)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $attachment->original_name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $attachment->file_type)) }} · {{ optional($attachment->created_at)->format('d/m/Y H:i') }} · {{ $attachment->uploader?->name ?: 'Sistema' }}</div>
                                @if($attachment->description)
                                    <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $attachment->description }}</div>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('contratos.attachments.download', [$item, $attachment]) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">Baixar</a>
                                <form method="post" action="{{ route('contratos.attachments.delete', [$item, $attachment]) }}">@csrf @method('DELETE')<button onclick="return confirm('Excluir este anexo?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button></form>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-ancora.empty-state icon="fa-solid fa-paperclip" title="Sem anexos" subtitle="Envie documentos vinculados a este contrato sem afetar o historico principal." />
                @endforelse
            </div>
        </div>
    </div>
@endif
@endsection
