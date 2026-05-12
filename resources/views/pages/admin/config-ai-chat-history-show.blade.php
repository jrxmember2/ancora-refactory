@extends('layouts.app')

@php
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
    $portalUser = $message->conversation?->portalUser;
    $condominium = $message->conversation?->condominium;
    $fallbackDocuments = collect($message->meta_json['documents'] ?? []);
@endphp

@section('content')
<div class="space-y-6">
    <x-ancora.section-header title="Detalhe da Consulta" subtitle="Rastreabilidade completa da pergunta, resposta, modelo, fontes e marcacoes internas." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('config.ai.chat-history.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar para Historico</span>
        </a>
        <a href="{{ route('config.ai.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-brain"></i>
            <span>Voltar para IA</span>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Data e hora</div>
            <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $message->created_at?->format('d/m/Y H:i:s') ?: 'n/d' }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Condominio</div>
            <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $condominium?->name ?: 'Sem condominio' }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Usuario</div>
            <div class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $portalUser?->name ?: 'Usuario nao localizado' }}</div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $portalUser?->login_key ?: '-' }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Status</div>
            <div class="mt-2">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $message->status === 'error' ? 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300' : 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' }}">
                    {{ $message->status === 'error' ? 'Erro' : 'Sucesso' }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Pergunta</h3>
                <div class="mt-4 whitespace-pre-line rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm leading-7 text-gray-800 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-100">
                    {{ $question !== '' ? $question : 'Pergunta nao localizada no historico.' }}
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resposta</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Conteudo entregue ao usuario pelo fluxo da Leme.</p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <div>Provedor: <span class="font-semibold text-gray-900 dark:text-white">{{ strtoupper((string) ($message->provider ?: '-')) }}</span></div>
                        <div class="mt-1">Modelo: <span class="font-semibold text-gray-900 dark:text-white">{{ $message->model ?: 'n/d' }}</span></div>
                        <div class="mt-1">Tokens totais: <span class="font-semibold text-gray-900 dark:text-white">{{ $message->resolvedTokensTotal() !== null ? number_format($message->resolvedTokensTotal(), 0, ',', '.') : 'n/d' }}</span></div>
                    </div>
                </div>

                <div class="mt-4 whitespace-pre-line rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm leading-7 text-gray-800 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-100">
                    {{ $message->content }}
                </div>

                @if($message->errorText() !== '')
                    <div class="mt-4 rounded-2xl border border-error-200 bg-error-50 px-4 py-4 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-500/10 dark:text-error-300">
                        <div class="font-semibold">Erro registrado</div>
                        <div class="mt-2 whitespace-pre-line">{{ $message->errorText() }}</div>
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Documentos usados</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fontes documentais rastreadas para esta consulta.</p>

                @if($documentSources->isNotEmpty())
                    <div class="mt-5 space-y-3">
                        @foreach($documentSources as $entry)
                            @php($source = $entry['source'])
                            <div class="rounded-2xl border border-gray-200 px-4 py-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $source->documentLabel() }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Origem: {{ $source->source_type === 'global_document' ? 'Base Legal Global' : 'Documento do condominio' }}
                                </div>
                                @if(filled($source->document_kind))
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tipo: {{ \App\Support\AiDocumentCatalog::documentKindLabel($source->document_kind) }}</div>
                                @endif
                                @if(!empty($entry['chunks_used']))
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Chunks usados: {{ implode(', ', $entry['chunks_used']) }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @elseif($fallbackDocuments->isNotEmpty())
                    <div class="mt-5 space-y-3">
                        @foreach($fallbackDocuments as $document)
                            <div class="rounded-2xl border border-gray-200 px-4 py-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $document['title'] ?? ($document['document_kind_label'] ?? 'Documento') }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Origem: {{ ($document['source_type'] ?? '') === 'global_document' ? 'Base Legal Global' : 'Documento do condominio' }}</div>
                                @if(!empty($document['document_kind_label']))
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tipo: {{ $document['document_kind_label'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-5 rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-400">
                        Nenhuma fonte documental foi registrada para esta consulta.
                    </div>
                @endif
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Marcacoes internas</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use estas marcacoes para curadoria, auditoria e futuras FAQs.</p>

                <form method="post" action="{{ route('config.ai.chat-history.update', $message) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')

                    <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="is_relevant" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('is_relevant', $message->is_relevant))>
                        <span>
                            <span class="block font-medium">Relevante</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Marca consultas boas para referencia interna.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="requires_legal_review" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('requires_legal_review', $message->requires_legal_review))>
                        <span>
                            <span class="block font-medium">Requer analise juridica</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Sinaliza respostas que merecem revisao humana.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="is_faq_candidate" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('is_faq_candidate', $message->is_faq_candidate))>
                        <span>
                            <span class="block font-medium">Duvida frequente</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Destaca consultas boas para base recorrente ou FAQ.</span>
                        </span>
                    </label>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacao interna</label>
                        <textarea name="internal_note" rows="7" class="{{ $textareaClass }}" placeholder="Notas internas, qualidade da resposta, ajuste futuro de prompt, risco juridico...">{{ old('internal_note', $message->internal_note) }}</textarea>
                        @error('internal_note')
                            <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="{{ $buttonClass }} w-full">Salvar marcacoes</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Metadados tecnicos</h3>
                <dl class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Conversation ID</dt>
                        <dd class="mt-1">{{ $message->conversation?->id ?: 'n/d' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Message ID</dt>
                        <dd class="mt-1">{{ $message->id }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Tokens de entrada</dt>
                        <dd class="mt-1">{{ $message->input_tokens !== null ? number_format($message->input_tokens, 0, ',', '.') : 'n/d' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Tokens de saida</dt>
                        <dd class="mt-1">{{ $message->output_tokens !== null ? number_format($message->output_tokens, 0, ',', '.') : 'n/d' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-gray-900 dark:text-white">Chunks usados</dt>
                        <dd class="mt-1">{{ number_format((int) ($message->source_chunks_count ?? 0), 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
</div>
@endsection
