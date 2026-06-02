@php
    $agendaOverdue = (int) (($agendaSummary ?? [])['overdue_count'] ?? 0);
    $agendaUpcoming = (int) (($agendaSummary ?? [])['upcoming_count'] ?? 0);
    $agendaFatal = (int) (($agendaSummary ?? [])['fatal_count'] ?? 0);
    $agendaShow = $agendaOverdue > 0 || $agendaUpcoming > 0;
    $agendaUrgent = $agendaOverdue > 0;

    $agendaParts = [];
    if ($agendaOverdue > 0) {
        $agendaParts[] = $agendaOverdue . ' prazo(s) atrasado(s)';
    }
    if ($agendaUpcoming > 0) {
        $agendaParts[] = $agendaUpcoming . ' compromisso(s) nos proximos dias';
    }
    $agendaHeadline = implode(' · ', $agendaParts);
    $agendaDetail = ($agendaFatal > 0 ? 'Inclui ' . $agendaFatal . ' prazo(s) fatal(is). ' : '')
        . 'Confira sua agenda para nao perder nenhum prazo.';

    $agendaWrapClass = $agendaUrgent
        ? 'border-error-200 bg-error-50 dark:border-error-900/60 dark:bg-error-500/10'
        : 'border-brand-200 bg-brand-50 dark:border-brand-900/60 dark:bg-brand-500/10';
    $agendaIconClass = $agendaUrgent ? 'bg-error-500' : 'bg-brand-500';
@endphp

@if($agendaShow)
    <div class="mb-6 rounded-3xl border p-5 shadow-theme-sm {{ $agendaWrapClass }}">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-theme-xs {{ $agendaIconClass }}">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div>
                    <div class="text-base font-semibold text-gray-900 dark:text-white">{{ $agendaHeadline }}</div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $agendaDetail }}</div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                @if($agendaOverdue > 0)
                    <a href="{{ route('agenda.index', ['overdue_only' => 1]) }}" class="rounded-xl bg-error-500 px-4 py-3 text-sm font-medium text-white hover:opacity-90">Ver atrasados</a>
                @endif
                <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/[0.05]">Abrir agenda</a>
            </div>
        </div>
    </div>
@endif
