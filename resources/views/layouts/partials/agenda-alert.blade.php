@if(!empty($agendaSummary) && (($agendaSummary['overdue_count'] ?? 0) > 0 || ($agendaSummary['upcoming_count'] ?? 0) > 0))
    @php
        $overdue = (int) ($agendaSummary['overdue_count'] ?? 0);
        $upcoming = (int) ($agendaSummary['upcoming_count'] ?? 0);
        $fatal = (int) ($agendaSummary['fatal_count'] ?? 0);
        $isUrgent = $overdue > 0;
    @endphp
    <div class="mb-6 rounded-3xl border p-5 shadow-theme-sm {{ $isUrgent ? 'border-error-200 bg-error-50 dark:border-error-900/60 dark:bg-error-500/10' : 'border-brand-200 bg-brand-50 dark:border-brand-900/60 dark:bg-brand-500/10' }}">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-theme-xs {{ $isUrgent ? 'bg-error-500' : 'bg-brand-500' }}">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <div>
                    <div class="text-base font-semibold text-gray-900 dark:text-white">
                        @if($overdue > 0){{ $overdue }} prazo(s) atrasado(s)@endif
                        @if($overdue > 0 && $upcoming > 0) · @endif
                        @if($upcoming > 0){{ $upcoming }} compromisso(s) nos proximos dias@endif
                    </div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        @if($fatal > 0)Inclui {{ $fatal }} prazo(s) fatal(is). @endif
                        Confira sua agenda para nao perder nenhum prazo.
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                @if($overdue > 0)
                    <a href="{{ route('agenda.index', ['overdue_only' => 1]) }}" class="rounded-xl bg-error-500 px-4 py-3 text-sm font-medium text-white hover:opacity-90">Ver atrasados</a>
                @endif
                <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/[0.05]">Abrir agenda</a>
            </div>
        </div>
    </div>
@endif
