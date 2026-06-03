@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Agenda" subtitle="Prazos, audiencias, reunioes e compromissos do escritorio.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('agenda.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Ver em lista</a>
        <a href="{{ route('agenda.create') }}" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo compromisso</a>
    </div>
</x-ancora.section-header>

@if(!empty($feedUrl))
<details class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">Assinar no Google Agenda / Outlook / Apple</summary>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Copie o link abaixo e adicione como "assinar por URL" no seu app de calendario. Ele atualiza sozinho com seus prazos e compromissos.</p>
    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
        <input id="agenda-feed-url" type="text" readonly value="{{ $feedUrl }}" class="h-11 w-full rounded-xl border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
        <button type="button" onclick="const i=document.getElementById('agenda-feed-url');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.textContent='Copiado!';" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Copiar link</button>
    </div>
    <p class="mt-2 text-xs text-gray-400">Mantenha este link privado: quem tiver acesso vera seus compromissos.</p>
</details>
@endif

@if(!empty($calendarIntegrations))
<div class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Sincronizar com Google Agenda / Outlook</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Conecte sua conta para que seus compromissos sejam enviados automaticamente ao seu calendario.</p>
    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        @foreach($calendarIntegrations as $integration)
            <div class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-700">
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $integration['label'] }}</div>
                    @if($integration['connection'])
                        <div class="text-xs text-success-600 dark:text-success-300">Conectado{{ $integration['connection']->account_email ? ' (' . $integration['connection']->account_email . ')' : '' }}</div>
                    @else
                        <div class="text-xs text-gray-400">Nao conectado</div>
                    @endif
                </div>
                @if($integration['connection'])
                    <form method="post" action="{{ route('agenda.calendar.disconnect', ['provider' => $integration['key']]) }}">
                        @csrf
                        <button class="rounded-lg border border-error-300 px-3 py-2 text-xs font-medium text-error-700 dark:border-error-800 dark:text-error-300">Desconectar</button>
                    </form>
                @else
                    <a href="{{ route('agenda.calendar.connect', ['provider' => $integration['key']]) }}" class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white">Conectar</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('agenda.calendar', ['month' => $prevMonth->format('Y-m')]) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">&larr; {{ $prevMonth->translatedFormat('M/Y') }}</a>
        <h3 class="text-base font-semibold capitalize text-gray-900 dark:text-white">{{ $reference->translatedFormat('F \d\e Y') }}</h3>
        <a href="{{ route('agenda.calendar', ['month' => $nextMonth->format('Y-m')]) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">{{ $nextMonth->translatedFormat('M/Y') }} &rarr;</a>
    </div>

    <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl border border-gray-100 bg-gray-100 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
        @foreach(['Dom','Seg','Ter','Qua','Qui','Sex','Sab'] as $weekday)
            <div class="bg-white py-2 dark:bg-gray-900">{{ $weekday }}</div>
        @endforeach
    </div>

    <div class="grid grid-cols-7 gap-px overflow-hidden rounded-b-xl border-x border-b border-gray-100 bg-gray-100 dark:border-gray-800 dark:bg-gray-800">
        @foreach($weeks as $week)
            @foreach($week as $day)
                <div class="min-h-[110px] bg-white p-2 align-top dark:bg-gray-900 {{ $day['in_month'] ? '' : 'opacity-50' }}">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs font-semibold {{ $day['is_today'] ? 'flex h-6 w-6 items-center justify-center rounded-full bg-brand-500 text-white' : 'text-gray-500 dark:text-gray-400' }}">{{ $day['date']->day }}</span>
                        <a href="{{ route('agenda.create', ['month' => $reference->format('Y-m')]) }}" class="text-xs text-gray-300 hover:text-brand-500">+</a>
                    </div>
                    <div class="space-y-1">
                        @foreach($day['events'] as $event)
                            @php
                                $overdue = $event->isOverdue();
                                $hasColor = $event->hasColor();
                                $chip = $overdue ? 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300'
                                    : ($event->is_fatal ? 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300'
                                    : 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200');
                                $style = $hasColor ? 'background-color:' . $event->color . ';color:' . $event->textColor() . ';' : '';
                            @endphp
                            <a href="{{ route('agenda.show', $event) }}" class="block truncate rounded-md px-2 py-1 text-xs {{ $hasColor ? '' : $chip }}" style="{{ $style }}" title="{{ $event->title }}">
                                <span class="font-medium">{{ $event->all_day ? '' : $event->start_at->format('H:i') }}</span> {{ $event->title }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</div>
@endsection
