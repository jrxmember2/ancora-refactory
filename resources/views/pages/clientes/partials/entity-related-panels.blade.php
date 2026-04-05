@if(isset($attachments) && $attachments->count())
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Anexos</h3>
        <div class="mt-4 space-y-3">
            @foreach($attachments as $attachment)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $attachment->original_name }}</div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Papel: {{ ucfirst($attachment->file_role ?: 'documento') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('clientes.attachments.download', $attachment) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">Baixar</a>
                            <form method="post" action="{{ route('clientes.attachments.delete', $attachment) }}">
                                @csrf
                                @method('DELETE')
                                <button onclick="return confirm('Excluir este anexo?')" class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-600 dark:text-error-300">Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if(isset($timeline) && $timeline->count())
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Timeline</h3>
        <div class="mt-4 space-y-3">
            @foreach($timeline as $event)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                    <div class="text-sm text-gray-700 dark:text-gray-200">{{ $event->note }}</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ optional($event->created_at)->format('d/m/Y H:i') }} · {{ $event->user_email }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endif
