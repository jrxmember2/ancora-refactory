@extends('layouts.app')

@section('content')
<x-ancora.section-header title="Agenda" subtitle="Prazos, audiencias, reunioes e compromissos do escritorio.">
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('agenda.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Ver em lista</a>
        <button type="button" onclick="window.agendaCreate && window.agendaCreate()" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Novo compromisso</button>
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

<style>
    [x-cloak]{display:none !important;}
    /* Ajustes do FullCalendar para o tema escuro */
    .dark .fc{color:#e5e7eb;}
    .dark .fc .fc-col-header-cell-cushion,.dark .fc .fc-daygrid-day-number,.dark .fc .fc-list-day-text{color:#e5e7eb;}
    .dark .fc-theme-standard td,.dark .fc-theme-standard th,.dark .fc-theme-standard .fc-scrollgrid{border-color:#374151;}
    .dark .fc .fc-day-today{background:rgba(59,130,246,0.12) !important;}
    .fc .fc-button-primary{background:#3b82f6;border-color:#3b82f6;}
    .fc .fc-button-primary:not(:disabled).fc-button-active,.fc .fc-button-primary:hover{background:#2563eb;border-color:#2563eb;}
    .fc a{cursor:pointer;}
</style>

<div x-data="{
        open: {{ ($errors->any() && old('_modal')) ? 'true' : 'false' }},
        close(){ this.open = false; },
        prefill(date){ this.$nextTick(() => { const el = document.getElementById('agenda-start-modal'); if(el && date){ el.value = (date.length <= 10) ? (date + 'T09:00') : date.slice(0,16); } }); }
    }"
    x-on:agenda-create.window="open = true; prefill($event.detail && $event.detail.date)">

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div id="agenda-calendar">
            <div id="agenda-calendar-fallback" class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Carregando calendario...</div>
        </div>
    </div>

    {{-- Modal de criacao (desktop). No mobile/app a criacao usa a pagina normal. --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[99999] flex items-start justify-center overflow-y-auto p-4 sm:p-6" style="display:none;">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="close()"></div>
        <div class="relative z-10 mt-6 w-full max-w-3xl rounded-2xl bg-gray-50 p-5 shadow-2xl dark:bg-gray-900 sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Novo compromisso</h3>
                <button type="button" @click="close()" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-white/[0.05]"><i class="fa-solid fa-xmark"></i></button>
            </div>
            @if($errors->any() && old('_modal'))
                <div class="mb-4 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300">
                    <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @include('pages.agenda.partials._form', ['mode' => 'create', 'item' => null, 'inModal' => true, 'parentProcess' => null])
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/index.global.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/locales/pt-br.global.min.js"></script>
<script>
(function(){
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const eventsUrl = @json(route('agenda.events.json'));
    const createUrl = @json(route('agenda.create'));
    const listUrl = @json(route('agenda.index'));
    const rescheduleBase = @json(url('/agenda'));

    // Abre criacao: no mobile vai para a pagina; no desktop abre o modal.
    window.agendaCreate = function(date){
        if (window.innerWidth < 768){
            const url = new URL(createUrl, window.location.origin);
            if (date) url.searchParams.set('start', date);
            window.location = url.toString();
            return;
        }
        window.dispatchEvent(new CustomEvent('agenda-create', { detail: { date: date || null } }));
    };

    function toLocalIso(d){
        if(!d) return null;
        return new Date(d.getTime() - d.getTimezoneOffset()*60000).toISOString().slice(0,19);
    }

    function reschedule(info){
        const ev = info.event;
        fetch(rescheduleBase + '/' + ev.id + '/reagendar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ start_at: toLocalIso(ev.start), end_at: toLocalIso(ev.end), all_day: ev.allDay ? 1 : 0 })
        }).then(r => { if(!r.ok) info.revert(); }).catch(() => info.revert());
    }

    document.addEventListener('DOMContentLoaded', function(){
        const el = document.getElementById('agenda-calendar');
        if(!el) return;

        if (typeof FullCalendar === 'undefined'){
            el.innerHTML = '<div class="p-8 text-center text-sm text-error-600 dark:text-error-300">Nao foi possivel carregar o calendario (script externo bloqueado pela rede/CSP). <a href="' + listUrl + '" class="underline">Abrir em lista</a>.</div>';
            return;
        }

        el.innerHTML = '';
        const calendar = new FullCalendar.Calendar(el, {
            locale: 'pt-br',
            timeZone: 'local',
            initialView: 'dayGridMonth',
            height: 'auto',
            nowIndicator: true,
            editable: true,
            dayMaxEvents: true,
            headerToolbar: {
                left: 'novo today prev,next',
                center: 'title',
                right: 'multiMonthYear,dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: { today: 'Hoje', month: 'Mes', week: 'Semana', day: 'Dia' },
            views: { multiMonthYear: { buttonText: 'Ano' } },
            customButtons: {
                novo: { text: '+ Novo', click: function(){ window.agendaCreate(); } }
            },
            events: { url: eventsUrl },
            dateClick: function(info){ window.agendaCreate(info.dateStr); },
            eventDrop: reschedule,
            eventResize: reschedule
        });
        calendar.render();
    });
})();
</script>
@endpush
@endsection
