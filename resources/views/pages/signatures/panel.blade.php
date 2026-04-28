@php
    $requestStatusLabels = \App\Services\DocumentSignatureService::requestStatusLabels();
    $signerStatusLabels = \App\Services\DocumentSignatureService::signerStatusLabels();
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Assinatura digital</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Acompanhe quem ja assinou, sincronize o status com a Assinafy e baixe os artefatos finais quando o processo for concluido.</p>
        </div>
        <a href="{{ $createUrl }}" class="rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200">Novo envio</a>
    </div>

    <div class="mt-5 space-y-4">
        @forelse($requests as $signature)
            @php
                $statusClasses = match ($signature->status) {
                    'certificated' => 'border-success-200 bg-success-50 text-success-700 dark:border-success-800/70 dark:bg-success-500/10 dark:text-success-300',
                    'rejected_by_signer', 'rejected_by_user', 'failed', 'expired' => 'border-error-200 bg-error-50 text-error-700 dark:border-error-800/70 dark:bg-error-500/10 dark:text-error-300',
                    default => 'border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-800/70 dark:bg-warning-500/10 dark:text-warning-200',
                };
                $signedCount = $signature->signers->where('completed', true)->count();
            @endphp
            <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $statusClasses }}">
                                {{ $requestStatusLabels[$signature->status] ?? $signature->status }}
                            </span>
                            <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-600 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300">
                                {{ $signedCount }}/{{ $signature->signers->count() }} assinatura(s)
                            </span>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $signature->document_name }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Solicitado em {{ optional($signature->created_at)->format('d/m/Y H:i') ?: '-' }}
                                @if($signature->creator)
                                    - {{ $signature->creator->name }}
                                @endif
                                @if($signature->completed_at)
                                    - Concluido em {{ optional($signature->completed_at)->format('d/m/Y H:i') }}
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
                        <form method="post" action="{{ route($routePrefix.'.sync', [$ownerRouteParam => $owner, 'signature' => $signature]) }}">
                            @csrf
                            <button class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Sincronizar</button>
                        </form>
                        <a href="{{ route($routePrefix.'.download', [$ownerRouteParam => $owner, 'signature' => $signature, 'artifact' => 'original']) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Original</a>
                        @if($signature->signed_pdf_path || $signature->status === 'certificated')
                            <a href="{{ route($routePrefix.'.download', [$ownerRouteParam => $owner, 'signature' => $signature, 'artifact' => 'signed']) }}" class="rounded-lg bg-success-600 px-3 py-2 text-xs font-medium text-white">Assinado</a>
                        @endif
                        @if($signature->certificate_pdf_path || $signature->status === 'certificated')
                            <a href="{{ route($routePrefix.'.download', [$ownerRouteParam => $owner, 'signature' => $signature, 'artifact' => 'certificate']) }}" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:text-brand-200">Certificado</a>
                        @endif
                        @if($signature->bundle_pdf_path || $signature->status === 'certificated')
                            <a href="{{ route($routePrefix.'.download', [$ownerRouteParam => $owner, 'signature' => $signature, 'artifact' => 'bundle']) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Pacote</a>
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
                                            @if($signer->document_number || $signer->phone)
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $signer->document_number ?: '-' }}{{ $signer->phone ? ' - '.$signer->phone : '' }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] {{ $signerClasses }}">
                                                {{ $signerStatusLabels[$signer->status] ?? $signer->status }}
                                            </span>
                                            @if($signer->signing_url)
                                                <a href="{{ $signer->signing_url }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]">Abrir link</a>
                                                <button type="button" data-copy-signature-link="{{ $signer->signing_url }}" class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-medium text-brand-700 dark:border-brand-800 dark:text-brand-200">Copiar link</button>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-gray-500 dark:text-gray-400 md:grid-cols-4">
                                        <div>Convite: {{ optional($signer->requested_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                        <div>Visualizou: {{ optional($signer->viewed_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                        <div>Assinou: {{ optional($signer->signed_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                        <div>Recusou: {{ optional($signer->rejected_at)->format('d/m/Y H:i') ?: '-' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Eventos</div>
                        <div class="mt-3 space-y-3">
                            @forelse($signature->events->take(8) as $event)
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ str_replace('_', ' ', $event->event_type) }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ optional($event->received_at)->format('d/m/Y H:i') ?: '-' }}</div>
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
            <x-ancora.empty-state icon="fa-solid fa-file-signature" title="Nenhum envio realizado" subtitle="Envie o primeiro documento para assinatura digital e acompanhe tudo por aqui." />
        @endforelse
    </div>
</div>

@once
    @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-copy-signature-link]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const text = button.getAttribute('data-copy-signature-link') || '';
                    if (!text) {
                        return;
                    }

                    const originalLabel = button.textContent;

                    try {
                        await navigator.clipboard.writeText(text);
                        button.textContent = 'Link copiado';
                    } catch (error) {
                        button.textContent = 'Falhou ao copiar';
                    }

                    window.setTimeout(() => {
                        button.textContent = originalLabel;
                    }, 1800);
                });
            });
        });
        </script>
    @endpush
@endonce
