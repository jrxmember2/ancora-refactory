@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$item->title" :subtitle="$typeLabels[$item->type] ?? $item->type">
    <div class="flex flex-wrap gap-3">
        @if($item->status !== 'concluido')
        <form method="post" action="{{ route('agenda.complete', $item) }}">@csrf
            <button class="rounded-xl bg-success-500 px-4 py-3 text-sm font-medium text-white">Concluir</button>
        </form>
        @endif
        <a href="{{ route('agenda.ics', $item) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Adicionar ao calendario (.ics)</a>
        <a href="{{ route('agenda.edit', $item) }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Editar</a>
        <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
    </div>
</x-ancora.section-header>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-4 xl:col-span-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                <div><span class="text-gray-500">Inicio:</span> {{ $item->start_at->format('d/m/Y') }} {{ $item->all_day ? '(dia inteiro)' : $item->start_at->format('H:i') }}</div>
                <div><span class="text-gray-500">Termino:</span> {{ $item->end_at ? $item->end_at->format('d/m/Y H:i') : '-' }}</div>
                <div><span class="text-gray-500">Tipo:</span> {{ $typeLabels[$item->type] ?? $item->type }}</div>
                <div><span class="text-gray-500">Status:</span> {{ $item->isOverdue() ? 'Atrasado' : ($statusLabels[$item->status] ?? $item->status) }}</div>
                <div><span class="text-gray-500">Prazo fatal:</span> {{ $item->is_fatal ? 'Sim' : 'Nao' }}</div>
                <div><span class="text-gray-500">Prioridade:</span> {{ ucfirst($item->priority ?: 'normal') }}</div>
                <div><span class="text-gray-500">Local:</span> {{ $item->location ?: '-' }}</div>
                <div><span class="text-gray-500">Lembrete:</span> {{ $item->reminder_minutes ? $item->reminder_minutes . ' min antes' : '-' }}</div>
            </div>
            @if($item->description)
                <div class="mt-4 border-t border-gray-100 pt-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">{!! nl2br(e($item->description)) !!}</div>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Vinculos</h3>
            <div class="mt-3 space-y-2 text-sm">
                <div><span class="text-gray-500">Responsavel:</span> {{ $item->responsible?->name ?: '-' }}</div>
                <div><span class="text-gray-500">Solicitante:</span> {{ $item->requester?->name ?: '-' }}</div>
                <div><span class="text-gray-500">Processo:</span> @if($item->process)<a href="{{ route('processos.show', $item->process) }}" class="text-brand-600 underline dark:text-brand-300">{{ $item->process->process_number }}</a>@else-@endif</div>
                <div><span class="text-gray-500">Cliente:</span> {{ $item->client?->display_name ?: '-' }}</div>
                <div><span class="text-gray-500">Contrato:</span> @if($item->contract)<a href="{{ route('contratos.show', $item->contract) }}" class="text-brand-600 underline dark:text-brand-300">{{ $item->contract->code ?: $item->contract->title }}</a>@else-@endif</div>
                @if($item->status === 'concluido')<div><span class="text-gray-500">Concluido em:</span> {{ $item->completed_at?->format('d/m/Y H:i') }} ({{ $item->completer?->name }})</div>@endif
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="post" action="{{ route('agenda.delete', $item) }}">@csrf @method('DELETE')
                <button onclick="return confirm('Remover este compromisso?')" class="w-full rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 dark:border-error-800 dark:text-error-300">Excluir compromisso</button>
            </form>
        </div>
    </div>
</div>
@endsection
