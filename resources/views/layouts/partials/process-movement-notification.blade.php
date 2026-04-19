@if(!empty($processMovementNotification))
    <div class="mb-6 rounded-3xl border border-brand-200 bg-gradient-to-r from-brand-50 via-white to-warning-50 p-5 shadow-theme-sm dark:border-brand-900/60 dark:from-brand-500/10 dark:via-white/[0.03] dark:to-warning-500/10">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-500 text-white shadow-theme-xs">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <div class="text-base font-semibold text-gray-900 dark:text-white">
                        Há {{ $processMovementNotification['count'] }} nova(s) movimentação(ões) em {{ $processMovementNotification['case_count'] }} processo(s).
                    </div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Última movimentação identificada em {{ optional($processMovementNotification['latest_at'])->format('d/m/Y H:i') }}.
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="button" onclick="document.getElementById('process-movement-notification-modal')?.showModal()" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                    Ver
                </button>
                <form method="post" action="{{ route('processos.notifications.ack') }}">
                    @csrf
                    <button class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/[0.05]">
                        Ciente
                    </button>
                </form>
            </div>
        </div>
    </div>

    <dialog id="process-movement-notification-modal" class="fixed inset-0 m-auto max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-0 text-left shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 p-6 dark:border-gray-800">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Processos com novas movimentações</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Abra o processo para conferir os andamentos importados ou cadastrados recentemente.</p>
                </div>
                <button type="button" onclick="document.getElementById('process-movement-notification-modal')?.close()" class="rounded-full border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.05]">Fechar</button>
            </div>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($processMovementNotification['cases'] as $caseNotification)
                <div class="p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ $caseNotification['url'] }}" class="text-base font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200">
                                    {{ $caseNotification['title'] }}
                                </a>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $caseNotification['status'] }}</span>
                                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ $caseNotification['count'] }} nova(s)</span>
                            </div>
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-200">
                                {{ $caseNotification['client'] }} x {{ $caseNotification['adverse'] }}
                            </div>
                            <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Último andamento: {{ $caseNotification['latest_description'] }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ optional($caseNotification['latest_at'])->format('d/m/Y H:i') }}
                            </div>
                        </div>
                        <a href="{{ $caseNotification['url'] }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.05]">
                            Abrir processo
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-3 border-t border-gray-100 p-6 dark:border-gray-800 sm:flex-row sm:justify-end">
            <form method="post" action="{{ route('processos.notifications.ack') }}">
                @csrf
                <button class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600 sm:w-auto">Marcar tudo como ciente</button>
            </form>
        </div>
    </dialog>
@endif
