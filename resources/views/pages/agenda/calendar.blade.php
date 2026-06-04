@extends('layouts.app')

@section('content')
@php $hasCalendarSettings = !empty($feedUrl) || !empty($calendarIntegrations); @endphp

<div x-data="{ settingsOpen: false }">
    <x-ancora.section-header title="Agenda" subtitle="Prazos, audiencias, reunioes e compromissos do escritorio.">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('agenda.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Ver em lista</a>
            @if($hasCalendarSettings)
            <button type="button" @click="settingsOpen = true" title="Integracoes de calendario" aria-label="Integracoes de calendario"
                class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]">
                <i class="fa-solid fa-gear"></i>
            </button>
            @endif
        </div>
    </x-ancora.section-header>

    @if($hasCalendarSettings)
    {{-- Modal: integracoes de calendario (assinatura .ics + sincronizacao Google/Outlook) --}}
    <div x-show="settingsOpen" x-cloak class="fixed inset-0 z-[99999] flex items-start justify-center overflow-y-auto p-4 sm:p-6" style="display:none;">
        <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="settingsOpen = false"></div>
        <div class="relative z-10 mt-6 w-full max-w-2xl rounded-2xl bg-gray-50 p-5 shadow-2xl dark:bg-gray-900 sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Integracoes de calendario</h3>
                <button type="button" @click="settingsOpen = false" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-white/[0.05]"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="space-y-5">
                @if(!empty($feedUrl))
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Assinar no Google Agenda / Outlook / Apple</h4>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Copie o link abaixo e adicione como "assinar por URL" no seu app de calendario. Ele atualiza sozinho com seus prazos e compromissos.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input id="agenda-feed-url" type="text" readonly value="{{ $feedUrl }}" class="h-11 w-full rounded-xl border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                        <button type="button" onclick="const i=document.getElementById('agenda-feed-url');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.textContent='Copiado!';" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white">Copiar link</button>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Mantenha este link privado: quem tiver acesso vera seus compromissos.</p>
                </div>
                @endif

                @if(!empty($calendarIntegrations))
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Sincronizar com Google Agenda / Outlook</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Conecte sua conta para que seus compromissos sejam enviados automaticamente ao seu calendario.</p>
                    <div class="mt-3 space-y-3">
                        @foreach($calendarIntegrations as $integration)
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $integration['label'] }}</div>
                                    @if($integration['connection'])
                                        <div class="truncate text-xs text-success-600 dark:text-success-300">Conectado{{ $integration['connection']->account_email ? ' (' . $integration['connection']->account_email . ')' : '' }}</div>
                                    @else
                                        <div class="text-xs text-gray-400">Nao conectado</div>
                                    @endif
                                </div>
                                @if($integration['connection'])
                                    <form method="post" action="{{ route('agenda.calendar.disconnect', ['provider' => $integration['key']]) }}" class="shrink-0">
                                        @csrf
                                        <button class="rounded-xl border border-error-300 px-4 py-3 text-sm font-medium text-error-700 hover:bg-error-50 dark:border-error-800 dark:text-error-300 dark:hover:bg-error-500/10">Desconectar</button>
                                    </form>
                                @else
                                    <a href="{{ route('agenda.calendar.connect', ['provider' => $integration['key']]) }}" class="shrink-0 rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">Conectar</a>
                                @endif
                            </div>
                            @if(!$loop->last)<div class="border-t border-gray-100 dark:border-gray-800"></div>@endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

<style>
    [x-cloak]{display:none !important;}

    /* ===== Tema FullCalendar estilo Google Agenda ===== */
    .fc{
        --fc-border-color:#e8eaed;
        --fc-today-bg-color:rgba(59,130,246,0.06);
        --fc-now-indicator-color:#ea4335;
        --fc-page-bg-color:transparent;
        font-family:inherit;
    }
    .dark .fc{
        --fc-border-color:#1f2937;
        --fc-today-bg-color:rgba(59,130,246,0.10);
        color:#e5e7eb;
    }
    .fc a{cursor:pointer;}
    .fc .fc-scrollgrid{border-radius:14px;overflow:hidden;}

    /* Cabecalho dos dias da semana */
    .fc .fc-col-header-cell{background:transparent;}
    .fc .fc-col-header-cell-cushion{
        padding:10px 6px;font-size:.7rem;font-weight:600;
        text-transform:uppercase;letter-spacing:.05em;color:#70757a;
    }
    .dark .fc .fc-col-header-cell-cushion{color:#9aa0a6;}

    /* Numero do dia */
    .fc .fc-daygrid-day-number{padding:6px 8px;font-size:.8rem;font-weight:500;color:#3c4043;}
    .dark .fc .fc-daygrid-day-number{color:#d1d5db;}
    .fc .fc-day-other .fc-daygrid-day-number{opacity:.4;}
    .fc .fc-daygrid-day-frame{min-height:5.5rem;}

    /* "Hoje": numero em circulo azul (Google) e fundo neutro */
    .fc .fc-day-today{background:var(--fc-today-bg-color) !important;}
    .fc .fc-day-today .fc-daygrid-day-number{
        background:#1a73e8;color:#fff;border-radius:9999px;margin:4px;padding:0;
        min-width:1.7rem;height:1.7rem;display:inline-flex;align-items:center;justify-content:center;
        font-weight:600;
    }

    /* Eventos como "chips" coloridos preenchidos */
    .fc .fc-daygrid-event{
        border:none;border-radius:6px;margin:1px 4px 2px;padding:1px 7px;
        font-size:.74rem;line-height:1.45;box-shadow:0 1px 1px rgba(60,64,67,.12);
        transition:filter .12s ease,transform .12s ease;
    }
    .fc .fc-daygrid-event:hover{filter:brightness(.93);}
    .fc .fc-event,.fc .fc-event-title,.fc .fc-event-time{font-weight:500 !important;}
    .fc .fc-daygrid-block-event .fc-event-time{opacity:.85;padding-right:3px;}
    .fc .fc-daygrid-event .fc-event-title{overflow:hidden;text-overflow:ellipsis;}

    /* Eventos na semana/dia (timeGrid) */
    .fc .fc-timegrid-event{
        border:none;border-radius:6px;box-shadow:0 1px 2px rgba(60,64,67,.18);padding:1px 5px;
    }
    .fc .fc-timegrid-event:hover{filter:brightness(.95);}

    /* Link "+N mais" */
    .fc .fc-daygrid-more-link{
        font-size:.72rem;font-weight:600;color:#5f6368;border-radius:4px;padding:0 4px;
    }
    .dark .fc .fc-daygrid-more-link{color:#9aa0a6;}
    .fc .fc-daygrid-more-link:hover{background:rgba(0,0,0,.06);}

    /* Titulo do mes/periodo */
    .fc .fc-toolbar-title{font-size:1.3rem;font-weight:600;color:#202124;}
    .dark .fc .fc-toolbar-title{color:#f1f3f4;}
    .fc .fc-toolbar.fc-header-toolbar{margin-bottom:1.25rem;flex-wrap:wrap;gap:.5rem;}

    /* Botoes da barra: claros, arredondados (Google) */
    .fc .fc-button-primary{
        background:#fff;border:1px solid #dadce0;color:#3c4043;box-shadow:none;
        text-transform:none;font-weight:500;font-size:.82rem;border-radius:9999px;padding:.45em .95em;
    }
    .fc .fc-button-primary:hover{background:#f1f3f4;border-color:#dadce0;color:#202124;}
    .fc .fc-button-primary:focus{box-shadow:0 0 0 3px rgba(26,115,232,.2);}
    .fc .fc-button-primary:not(:disabled).fc-button-active{background:#e8f0fe;border-color:#1a73e8;color:#1a73e8;}
    .fc .fc-button-primary:disabled{opacity:.5;}
    .dark .fc .fc-button-primary{background:#1f2937;border-color:#374151;color:#e5e7eb;}
    .dark .fc .fc-button-primary:hover{background:#374151;color:#fff;}
    .dark .fc .fc-button-primary:not(:disabled).fc-button-active{background:#1a73e8;border-color:#1a73e8;color:#fff;}

    /* Botao "+ Novo" em destaque (brand) */
    .fc .fc-novo-button{background:#1a73e8;border-color:#1a73e8;color:#fff;font-weight:600;}
    .fc .fc-novo-button:hover{background:#1765cc;border-color:#1765cc;color:#fff;}

    /* Popover do "+N mais" e tema escuro */
    .dark .fc .fc-popover{background:#1f2937;border-color:#374151;}
    .dark .fc .fc-popover-header{background:#111827;color:#e5e7eb;}
    .dark .fc .fc-list-day-text,.dark .fc .fc-list-day-side-text{color:#e5e7eb;}
    .dark .fc-theme-standard td,.dark .fc-theme-standard th,.dark .fc-theme-standard .fc-scrollgrid{border-color:#1f2937;}
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

{{-- Modal generico de visualizar/editar compromisso (carregado via fetch) --}}
<div id="agenda-event-modal" x-data="{ close(){ window.agendaModalClose && window.agendaModalClose(); } }"
     class="fixed inset-0 z-[99999] hidden items-start justify-center overflow-y-auto p-4 sm:p-6">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" data-agenda-close></div>
    <div class="relative z-10 mt-6 w-full max-w-3xl rounded-2xl bg-gray-50 p-5 shadow-2xl dark:bg-gray-900 sm:p-6">
        <div class="mb-4 flex items-center justify-between">
            <h3 id="agenda-event-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">Compromisso</h3>
            <button type="button" data-agenda-close class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-white/[0.05]"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="agenda-event-modal-body">
            <div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Carregando...</div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/fullcalendar/index.global.min.js') }}"></script>
<script src="{{ asset('vendor/fullcalendar/locales/pt-br.global.min.js') }}"></script>
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
            eventDisplay: 'block',
            dayMaxEvents: 3,
            fixedWeekCount: false,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
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
            eventClick: function(info){ info.jsEvent.preventDefault(); openAgendaModal(info.event.url, info.event.title); },
            eventDrop: reschedule,
            eventResize: reschedule
        });
        calendar.render();

        // ===== Modal de visualizar / editar compromisso (carregado via fetch) =====
        const modalEl = document.getElementById('agenda-event-modal');
        const modalBody = document.getElementById('agenda-event-modal-body');
        const modalTitle = document.getElementById('agenda-event-modal-title');

        function withModal(u){ const url = new URL(u, window.location.origin); url.searchParams.set('modal', '1'); return url.toString(); }
        function showModal(){ modalEl.classList.remove('hidden'); modalEl.classList.add('flex'); document.body.style.overflow = 'hidden'; }
        function closeAgendaModal(){ modalEl.classList.add('hidden'); modalEl.classList.remove('flex'); modalBody.innerHTML = ''; document.body.style.overflow = ''; }
        window.agendaModalClose = closeAgendaModal;

        function initAlpine(node){ if (window.Alpine && typeof window.Alpine.initTree === 'function') window.Alpine.initTree(node); }

        function openAgendaModal(url, title){
            modalTitle.textContent = title || 'Compromisso';
            modalBody.innerHTML = '<div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">Carregando...</div>';
            showModal();
            fetch(withModal(url), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, credentials: 'same-origin' })
                .then(r => r.text())
                .then(html => { modalBody.innerHTML = html; initAlpine(modalBody); })
                .catch(() => { modalBody.innerHTML = '<div class="p-8 text-center text-sm text-error-600 dark:text-error-300">Nao foi possivel carregar o compromisso.</div>'; });
        }

        function toast(msg){
            if (!msg) return;
            const t = document.createElement('div');
            t.className = 'fixed bottom-5 right-5 z-[100000] rounded-xl bg-gray-900 px-4 py-3 text-sm text-white shadow-2xl dark:bg-gray-700';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(() => { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2200);
        }

        function showFormErrors(form, errors){
            let box = form.querySelector('[data-modal-errors]');
            if (!box){
                box = document.createElement('div');
                box.setAttribute('data-modal-errors', '');
                box.className = 'mb-4 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300';
                form.prepend(box);
            }
            const msgs = [];
            Object.keys(errors || {}).forEach(k => (errors[k] || []).forEach(m => msgs.push(m)));
            box.innerHTML = '<ul class="list-disc pl-5">' + msgs.map(m => '<li>' + m + '</li>').join('') + '</ul>';
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Fechar: backdrop, botao X e ESC
        modalEl.addEventListener('click', function(e){ if (e.target.closest('[data-agenda-close]')) closeAgendaModal(); });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && !modalEl.classList.contains('hidden')) closeAgendaModal(); });

        // Links internos que trocam o fragmento (ex.: "Editar")
        modalBody.addEventListener('click', function(e){
            const a = e.target.closest('a[data-modal-load]');
            if (!a) return;
            e.preventDefault();
            openAgendaModal(a.getAttribute('href'), a.getAttribute('data-modal-title') || 'Compromisso');
        });

        // Envio de qualquer formulario do modal via fetch
        modalBody.addEventListener('submit', function(e){
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            e.preventDefault();

            const confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg && !window.confirm(confirmMsg)) return;

            const after = form.getAttribute('data-after') || 'close';
            const detailUrl = form.getAttribute('data-detail-url');
            const submitBtn = form.querySelector('button[type="submit"], button:not([type])');
            const fd = new FormData(form);
            fd.set('_modal', '1');
            if (submitBtn) submitBtn.disabled = true;

            fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: fd
            }).then(async function(r){
                if (r.ok){
                    let data = {};
                    try { data = await r.json(); } catch (_) {}
                    calendar.refetchEvents();
                    if (after === 'reload' && detailUrl) openAgendaModal(detailUrl, modalTitle.textContent);
                    else closeAgendaModal();
                    toast(data.message);
                } else if (r.status === 422){
                    const data = await r.json().catch(() => ({}));
                    if (submitBtn) submitBtn.disabled = false;
                    showFormErrors(form, data.errors || {});
                } else {
                    if (submitBtn) submitBtn.disabled = false;
                    showFormErrors(form, { erro: ['Nao foi possivel salvar. Tente novamente.'] });
                }
            }).catch(function(){
                if (submitBtn) submitBtn.disabled = false;
                showFormErrors(form, { erro: ['Falha de conexao.'] });
            });
        });
    });
})();
</script>
@endpush
@endsection
