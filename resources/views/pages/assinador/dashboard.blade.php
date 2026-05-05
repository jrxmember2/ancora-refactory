@extends('layouts.app')

@php
    $dateTime = fn ($value) => $value ? $value->format('d/m/Y H:i') : '-';
@endphp

@section('content')
<x-ancora.section-header title="Assinador Eletronico" subtitle="Central de assinatura digital para contratos, termos de acordo e documentos avulsos.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('assinador.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Documentos</a>
        <a href="{{ route('assinador.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Nova assinatura</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5">
    <x-ancora.stat-card label="Total de envios" :value="$summary['total']" hint="Todos os pedidos de assinatura monitorados pela central." icon="fa-solid fa-paper-plane" />
    <x-ancora.stat-card label="Aguardando assinatura" :value="$summary['pending']" hint="Documentos enviados aguardando a primeira conclusao." icon="fa-solid fa-hourglass-half" />
    <x-ancora.stat-card label="Parcialmente assinados" :value="$summary['partial']" hint="Ao menos um signatario ja concluiu." icon="fa-solid fa-signature" />
    <x-ancora.stat-card label="Assinados" :value="$summary['certificated']" hint="Documentos concluidos e certificados." icon="fa-solid fa-circle-check" />
    <x-ancora.stat-card label="Recusados" :value="$summary['rejected']" hint="Recusados por signatario ou cancelados pelo usuario." icon="fa-solid fa-circle-xmark" />
</div>

<div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
    <x-ancora.stat-card label="Falharam" :value="$summary['failed']" hint="Falhas na criacao ou sincronizacao da assinatura." icon="fa-solid fa-triangle-exclamation" />
    <x-ancora.stat-card label="Expirados" :value="$summary['expired']" hint="Convites vencidos na Assinafy." icon="fa-solid fa-clock-rotate-left" />
    <x-ancora.stat-card label="Enviados hoje" :value="$summary['sent_today']" hint="Novos envios registrados hoje." icon="fa-solid fa-calendar-day" />
    <x-ancora.stat-card label="Concluidos no mes" :value="$summary['completed_month']" hint="Assinaturas finalizadas na competencia atual." icon="fa-solid fa-calendar-check" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ultimos envios</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Assinaturas mais recentes criadas no sistema.</p>
            </div>
            <a href="{{ route('assinador.index') }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Ver tudo</a>
        </div>
        <div class="mt-5 space-y-3">
            @forelse($latestRequests as $signature)
                <a href="{{ $signature->view_url ?: route('assinador.index') }}" class="block rounded-2xl border border-gray-200 p-4 transition hover:border-brand-300 hover:bg-brand-50 dark:border-gray-800 dark:hover:bg-brand-500/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $signature->document_name }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $signature->source_label }} - {{ $signature->source_name }}</div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ $signature->signers->count() }} signatario(s)
                                @if($signature->creator)
                                    - {{ $signature->creator->name }}
                                @endif
                            </div>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $signature->status_badge_class }}">
                            {{ $statusLabels[$signature->status] ?? $signature->status }}
                        </span>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Enviado em {{ $dateTime($signature->created_at) }}
                        @if($signature->completed_at)
                            - Concluido em {{ $dateTime($signature->completed_at) }}
                        @endif
                    </div>
                </a>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-file-signature" title="Sem envios ainda" subtitle="As assinaturas de contratos, cobrancas e documentos avulsos aparecerao aqui." />
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Pendentes de acompanhamento</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Documentos que ainda dependem de assinatura ou certificacao.</p>
            </div>
            <a href="{{ route('assinador.index', ['status' => 'pending_signatures']) }}" class="text-sm font-medium text-brand-600 dark:text-brand-300">Filtrar</a>
        </div>
        <div class="mt-5 space-y-3">
            @forelse($pendingRequests as $signature)
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $signature->document_name }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $signature->source_label }} - {{ $signature->source_name }}</div>
                        </div>
                        <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $signature->status_badge_class }}">
                            {{ $statusLabels[$signature->status] ?? $signature->status }}
                        </span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="post" action="{{ route('assinador.signatures.sync', $signature) }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                            <button class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Sincronizar</button>
                        </form>
                        @if($signature->view_url)
                            <a href="{{ $signature->view_url }}" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:text-brand-200">Ver</a>
                        @endif
                    </div>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Ultima sincronizacao: {{ $dateTime($signature->last_synced_at) }}
                    </div>
                </div>
            @empty
                <x-ancora.empty-state icon="fa-solid fa-circle-check" title="Nenhuma pendencia" subtitle="Nao ha assinaturas aguardando acompanhamento neste momento." />
            @endforelse
        </div>
    </div>
</div>
@endsection
