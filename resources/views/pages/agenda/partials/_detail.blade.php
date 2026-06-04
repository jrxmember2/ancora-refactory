@php
    // Fragmento de detalhe do compromisso, injetado no modal do calendario (agenda.show?modal=1).
    $detailUrl = route('agenda.show', $item);
@endphp

<div class="space-y-5">
    {{-- Cabecalho / acoes --}}
    <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 dark:border-gray-800 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $item->title }}</h3>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $typeLabels[$item->type] ?? $item->type }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($item->status !== 'concluido')
            <form method="post" action="{{ route('agenda.complete', $item) }}" data-after="reload" data-detail-url="{{ $detailUrl }}">
                @csrf
                <input type="hidden" name="_modal" value="1">
                <button class="rounded-lg bg-success-500 px-3 py-2 text-sm font-medium text-white hover:bg-success-600">Concluir</button>
            </form>
            @endif
            <a href="{{ route('agenda.edit', $item) }}" data-modal-load data-modal-title="Editar compromisso"
               class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]">Editar</a>
            <a href="{{ route('agenda.ics', $item) }}" target="_blank" rel="noopener"
               class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.06]">.ics</a>
        </div>
    </div>

    {{-- Dados principais --}}
    <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
        <div><span class="text-gray-500 dark:text-gray-400">Inicio:</span> {{ $item->start_at->format('d/m/Y') }} {{ $item->all_day ? '(dia inteiro)' : $item->start_at->format('H:i') }}</div>
        <div><span class="text-gray-500 dark:text-gray-400">Termino:</span> {{ $item->end_at ? $item->end_at->format('d/m/Y H:i') : '-' }}</div>
        <div><span class="text-gray-500 dark:text-gray-400">Status:</span>
            @if($item->isOverdue())
                <span class="font-medium text-error-600 dark:text-error-300">Atrasado</span>
            @else
                {{ $statusLabels[$item->status] ?? $item->status }}
            @endif
        </div>
        <div><span class="text-gray-500 dark:text-gray-400">Prioridade:</span> {{ ucfirst($item->priority ?: 'normal') }}</div>
        <div><span class="text-gray-500 dark:text-gray-400">Prazo fatal:</span> {{ $item->is_fatal ? 'Sim' : 'Nao' }}</div>
        <div><span class="text-gray-500 dark:text-gray-400">Lembrete:</span> {{ $item->reminder_minutes ? $item->reminder_minutes . ' min antes' : '-' }}</div>
        <div class="md:col-span-2"><span class="text-gray-500 dark:text-gray-400">Local:</span> {{ $item->location ?: '-' }}</div>
    </div>

    @if($item->description)
        <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-700 dark:bg-white/[0.03] dark:text-gray-200">{!! nl2br(e($item->description)) !!}</div>
    @endif

    {{-- Vinculos --}}
    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
        <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Vinculos</h4>
        <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-2">
            <div><span class="text-gray-500 dark:text-gray-400">Responsavel:</span> {{ $item->responsible?->name ?: '-' }}</div>
            <div><span class="text-gray-500 dark:text-gray-400">Solicitante:</span> {{ $item->requester?->name ?: '-' }}</div>
            <div><span class="text-gray-500 dark:text-gray-400">Processo:</span> @if($item->process)<a href="{{ route('processos.show', $item->process) }}" class="text-brand-600 underline dark:text-brand-300">{{ $item->process->process_number }}</a>@else - @endif</div>
            <div><span class="text-gray-500 dark:text-gray-400">Cliente:</span> {{ $item->client?->display_name ?: '-' }}</div>
            <div><span class="text-gray-500 dark:text-gray-400">Contrato:</span> @if($item->contract)<a href="{{ route('contratos.show', $item->contract) }}" class="text-brand-600 underline dark:text-brand-300">{{ $item->contract->code ?: $item->contract->title }}</a>@else - @endif</div>
            @if($item->status === 'concluido')<div><span class="text-gray-500 dark:text-gray-400">Concluido em:</span> {{ $item->completed_at?->format('d/m/Y H:i') }} ({{ $item->completer?->name }})</div>@endif
            @if($item->participants->isNotEmpty())
                <div class="md:col-span-2"><span class="text-gray-500 dark:text-gray-400">Participantes:</span> {{ $item->participants->pluck('name')->implode(', ') }}</div>
            @endif
        </div>
    </div>

    {{-- Anexos --}}
    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
        <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Anexos</h4>
        <form method="post" action="{{ route('agenda.attachments.upload', $item) }}" enctype="multipart/form-data" data-after="reload" data-detail-url="{{ $detailUrl }}" class="flex flex-col gap-2 sm:flex-row">
            @csrf
            <input type="hidden" name="_modal" value="1">
            <input type="file" name="file" required class="w-full rounded-xl border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-700">
            <button class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Enviar</button>
        </form>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse($item->attachments as $attachment)
                <li class="flex items-center justify-between gap-2">
                    <a href="{{ route('agenda.attachments.download', [$item, $attachment]) }}" target="_blank" rel="noopener" class="truncate text-brand-600 underline dark:text-brand-300">{{ $attachment->original_name }}</a>
                    <form method="post" action="{{ route('agenda.attachments.delete', [$item, $attachment]) }}" data-after="reload" data-detail-url="{{ $detailUrl }}">
                        @csrf @method('DELETE')
                        <input type="hidden" name="_modal" value="1">
                        <button class="text-xs text-error-600 dark:text-error-300">remover</button>
                    </form>
                </li>
            @empty
                <li class="text-gray-400">Nenhum anexo.</li>
            @endforelse
        </ul>
    </div>

    {{-- Excluir --}}
    <form method="post" action="{{ route('agenda.delete', $item) }}" data-after="close" data-confirm="Remover este compromisso?">
        @csrf @method('DELETE')
        <input type="hidden" name="_modal" value="1">
        <button class="w-full rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 hover:bg-error-50 dark:border-error-800 dark:text-error-300 dark:hover:bg-error-500/10">Excluir compromisso</button>
    </form>
</div>
