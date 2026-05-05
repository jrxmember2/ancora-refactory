@extends('layouts.app')

@php
    $requestStatusLabels = \App\Services\DocumentSignatureService::requestStatusLabels();
    $signerStatusLabels = \App\Services\DocumentSignatureService::signerStatusLabels();
    $dateTime = fn ($value) => $value ? $value->format('d/m/Y H:i') : '-';
    $date = fn ($value) => $value ? $value->format('d/m/Y') : '-';
    $statusClasses = fn ($status) => match ($status) {
        'certificated' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300',
        'rejected_by_signer', 'rejected_by_user', 'failed', 'expired' => 'border-error-200 bg-error-50 text-error-700 dark:border-error-800/70 dark:bg-error-500/10 dark:text-error-300',
        default => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-800/70 dark:bg-warning-500/10 dark:text-warning-200',
    };
@endphp

@section('content')
<x-ancora.section-header :title="$documento->title ?: 'Documento avulso'" subtitle="Acompanhe o historico de assinatura, sincronize com a Assinafy e baixe os artefatos finais.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('assinador.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
        <a href="{{ route('assinador.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova assinatura</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[0.9fr,1.1fr]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dados do documento</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Informacoes salvas no cadastro avulso.</p>
                </div>
                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $statusClasses((string) $documento->status) }}">
                    {{ $requestStatusLabels[$documento->status] ?? $documento->status }}
                </span>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 text-sm text-gray-700 dark:text-gray-200 md:grid-cols-2">
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Titulo</div>
                    <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ $documento->title }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Categoria</div>
                    <div class="mt-1">{{ $documento->category ?: 'Nao informada' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Arquivo original</div>
                    <div class="mt-1">{{ $documento->original_name }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Tamanho</div>
                    <div class="mt-1">{{ $documento->file_size ? number_format($documento->file_size / 1024 / 1024, 2, ',', '.') . ' MB' : '-' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Cliente</div>
                    <div class="mt-1">{{ $documento->client?->display_name ?: 'Nao vinculado' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Condominio</div>
                    <div class="mt-1">{{ $documento->condominium?->name ?: 'Nao vinculado' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Criado em</div>
                    <div class="mt-1">{{ $dateTime($documento->created_at) }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Criado por</div>
                    <div class="mt-1">{{ $documento->creator?->name ?: '-' }}</div>
                </div>
            </div>

            @if($documento->description)
                <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50/70 p-4 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">
                    {{ $documento->description }}
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo da assinatura</h3>
            <div class="mt-5 space-y-3 text-sm text-gray-700 dark:text-gray-200">
                <div><span class="text-gray-500">Total de envios:</span> {{ $requests->count() }}</div>
                <div><span class="text-gray-500">Status atual:</span> {{ $requestStatusLabels[$documento->status] ?? $documento->status }}</div>
                <div><span class="text-gray-500">Ultimo envio:</span> {{ $latestRequest ? $dateTime($latestRequest->created_at) : '-' }}</div>
                <div><span class="text-gray-500">Ultima sincronizacao:</span> {{ $latestRequest ? $dateTime($latestRequest->last_synced_at) : '-' }}</div>
                <div><span class="text-gray-500">Concluido em:</span> {{ $latestRequest ? $dateTime($latestRequest->completed_at) : '-' }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Historico de assinaturas</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cada envio preserva os signatarios, eventos e artefatos retornados pela Assinafy.</p>
            </div>
            <a href="{{ route('assinador.create') }}" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Novo envio</a>
        </div>

        <div class="mt-5 space-y-4">
            @forelse($requests as $signature)
                @php($signedCount = $signature->signers->where('completed', true)->count())
                <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $statusClasses((string) $signature->status) }}">
                                    {{ $requestStatusLabels[$signature->status] ?? $signature->status }}
                                </span>
                                <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-600 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
                                    {{ $signedCount }}/{{ $signature->signers->count() }} assinatura(s)
                                </span>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $signature->document_name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Solicitado em {{ $dateTime($signature->created_at) }}
                                    @if($signature->creator)
                                        - {{ $signature->creator->name }}
                                    @endif
                                    @if($signature->completed_at)
                                        - Concluido em {{ $dateTime($signature->completed_at) }}
                                    @endif
                                </div>
                            </div>
                            @if($signature->signer_message)
                                <div class="rounded-xl border border-gray-200 bg-gray-50/70 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">
                                    {{ $signature->signer_message }}
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <form method="post" action="{{ route('assinador.signatures.sync', $signature) }}">
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                                <button class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Sincronizar</button>
                            </form>
                            <a href="{{ route('assinador.signatures.download', ['signature' => $signature, 'artifact' => 'original', 'redirect_to' => request()->getRequestUri()]) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Original</a>
                            @if($signature->signed_pdf_path || $signature->status === 'certificated')
                                <a href="{{ route('assinador.signatures.download', ['signature' => $signature, 'artifact' => 'signed', 'redirect_to' => request()->getRequestUri()]) }}" class="rounded-lg bg-success-600 px-3 py-2 text-xs font-medium text-white">Assinado</a>
                            @endif
                            @if($signature->certificate_pdf_path || $signature->status === 'certificated')
                                <a href="{{ route('assinador.signatures.download', ['signature' => $signature, 'artifact' => 'certificate', 'redirect_to' => request()->getRequestUri()]) }}" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:text-brand-200">Certificado</a>
                            @endif
                            @if($signature->bundle_pdf_path || $signature->status === 'certificated')
                                <a href="{{ route('assinador.signatures.download', ['signature' => $signature, 'artifact' => 'bundle', 'redirect_to' => request()->getRequestUri()]) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Pacote</a>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[1.2fr,0.8fr]">
                        <div>
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Signatarios</div>
                            <div class="mt-3 space-y-3">
                                @foreach($signature->signers as $signer)
                                    @php
                                        $signerClasses = match ($signer->status) {
                                            'signed' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300',
                                            'rejected' => 'border-error-200 bg-error-50 text-error-700 dark:border-error-800/70 dark:bg-error-500/10 dark:text-error-300',
                                            'viewed' => 'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-800/70 dark:bg-brand-500/10 dark:text-brand-200',
                                            default => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-800/70 dark:bg-warning-500/10 dark:text-warning-200',
                                        };
                                    @endphp
                                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $signer->name }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $signer->email }}{{ $signer->role_label ? ' - '.$signer->role_label : '' }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    Ordem {{ $signer->order_index ?: '-' }}
                                                    @if($signer->document_number)
                                                        - {{ $signer->document_number }}
                                                    @endif
                                                    @if($signer->phone)
                                                        - {{ $signer->phone }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $signerClasses }}">
                                                    {{ $signerStatusLabels[$signer->status] ?? $signer->status }}
                                                </span>
                                                @if($signer->signing_url)
                                                    <a href="{{ $signer->signing_url }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Abrir link</a>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-gray-500 dark:text-gray-400 md:grid-cols-4">
                                            <div>Convite: {{ $dateTime($signer->requested_at) }}</div>
                                            <div>Visualizou: {{ $dateTime($signer->viewed_at) }}</div>
                                            <div>Assinou: {{ $dateTime($signer->signed_at) }}</div>
                                            <div>Recusou: {{ $dateTime($signer->rejected_at) }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Eventos</div>
                            <div class="mt-3 space-y-3">
                                @forelse($signature->events->take(10) as $event)
                                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ str_replace('_', ' ', $event->event_type) }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $dateTime($event->received_at) }}</div>
                                        </div>
                                        @if($event->signer)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $event->signer->name }}</div>
                                        @endif
                                        @if($event->message)
                                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">{{ $event->message }}</div>
                                        @endif
                                    </div>
                                @empty
                                    <x-ancora.empty-state icon="fa-solid fa-wave-square" title="Sem eventos recebidos" subtitle="Quando a Assinafy enviar atualizacoes, elas aparecerao aqui." />
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-file-signature" title="Nenhum envio realizado" subtitle="Este documento ainda nao foi enviado para assinatura digital." />
            @endforelse
        </div>
    </div>
</div>
@endsection
