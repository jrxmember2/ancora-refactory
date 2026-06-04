<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgendaEventRequest;
use App\Http\Requests\UpdateAgendaEventRequest;
use App\Jobs\SyncAgendaEventToCalendarsJob;
use App\Models\AgendaEvent;
use App\Models\AgendaEventAttachment;
use App\Models\CalendarConnection;
use App\Models\ClientEntity;
use App\Models\Contract;
use App\Models\Demand;
use App\Models\ProcessCase;
use App\Models\User;
use App\Services\Calendar\CalendarProviders;
use App\Support\Agenda\AgendaCatalog;
use App\Support\Agenda\IcsBuilder;
use App\Support\AncoraAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgendaController extends Controller
{
    public function calendar(Request $request): View
    {
        $reference = $this->referenceMonth($request);
        $gridStart = $reference->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $reference->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $events = AgendaEvent::query()
            ->with(['responsible', 'process'])
            ->whereBetween('start_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
            ->orderBy('start_at')
            ->get()
            ->groupBy(fn (AgendaEvent $event) => $event->start_at->format('Y-m-d'));

        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $cursor->copy(),
                    'in_month' => $cursor->month === $reference->month,
                    'is_today' => $cursor->isToday(),
                    'events' => $events->get($key, collect()),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $user = AncoraAuth::user($request);
        $feedUrl = null;
        if ($user && Schema::hasColumn('users', 'calendar_feed_token')) {
            $feedUrl = route('agenda.feed', ['token' => $this->feedTokenForUser($user)]);
        }

        return view('pages.agenda.calendar', array_merge($this->formOptions(), [
            'title' => 'Agenda',
            'reference' => $reference,
            'weeks' => $weeks,
            'prevMonth' => $reference->copy()->subMonthNoOverflow(),
            'nextMonth' => $reference->copy()->addMonthNoOverflow(),
            'typeLabels' => AgendaCatalog::types(),
            'feedUrl' => $feedUrl,
            'calendarIntegrations' => $this->calendarIntegrations($user),
            // Dados para o modal de criacao embutido no calendario (desktop).
            'mode' => 'create',
            'item' => null,
            'draft' => [
                'type' => 'compromisso',
                'status' => 'aberto',
                'start_at' => now()->format('Y-m-d\TH:i'),
            ],
            'selectedParticipants' => [],
        ]));
    }

    /**
     * Eventos para o FullCalendar (carregados por intervalo via fetch).
     */
    public function eventsJson(Request $request): JsonResponse
    {
        $query = AgendaEvent::query()->with('responsible');

        try {
            if ($request->filled('start')) {
                $query->where('start_at', '>=', Carbon::parse($request->query('start')));
            }
            if ($request->filled('end')) {
                $query->where('start_at', '<=', Carbon::parse($request->query('end')));
            }
        } catch (\Throwable) {
            // intervalo invalido: ignora o filtro e devolve os mais recentes
        }

        $events = $query->orderBy('start_at')->limit(2000)->get();

        // Paleta por tipo (estilo Google Agenda) quando o evento nao tem cor propria.
        $typePalette = [
            'prazo' => '#3f51b5',       // azul indigo
            'audiencia' => '#0b8043',   // verde
            'reuniao' => '#039be5',     // azul claro
            'tarefa' => '#7986cb',      // lavanda
            'compromisso' => '#009688', // teal
            'diligencia' => '#f4511e',  // laranja
            'pericia' => '#8e24aa',     // roxo
            'outro' => '#616161',       // grafite
        ];

        return response()->json($events->map(function (AgendaEvent $event) use ($typePalette) {
            $bg = $event->hasColor()
                ? $event->color
                : ($event->isOverdue() ? '#ef4444'
                    : ($event->is_fatal ? '#f59e0b'
                        : ($typePalette[$event->type] ?? '#3b82f6')));
            $text = $event->hasColor() ? $event->textColor() : '#ffffff';

            return [
                'id' => $event->id,
                'title' => $event->title,
                'start' => optional($event->start_at)->toIso8601String(),
                'end' => optional($event->end_at)->toIso8601String(),
                'allDay' => (bool) $event->all_day,
                'url' => route('agenda.show', $event),
                'backgroundColor' => $bg,
                'borderColor' => $bg,
                'textColor' => $text,
                'extendedProps' => [
                    'fatal' => (bool) $event->is_fatal,
                    'overdue' => $event->isOverdue(),
                    'responsible' => $event->responsible->name ?? null,
                ],
            ];
        })->all());
    }

    /**
     * Reagenda um evento (drag-and-drop / resize no FullCalendar).
     */
    public function reschedule(Request $request, AgendaEvent $evento): JsonResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $data = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'all_day' => ['nullable', 'boolean'],
        ]);

        $evento->update([
            'start_at' => Carbon::parse($data['start_at']),
            'end_at' => !empty($data['end_at']) ? Carbon::parse($data['end_at']) : null,
            'all_day' => $request->boolean('all_day', (bool) $evento->all_day),
            'updated_by' => $user->id,
        ]);

        SyncAgendaEventToCalendarsJob::dispatch($evento->id, 'upsert');

        return response()->json(['ok' => true]);
    }

    /**
     * Estado das integracoes OAuth (Google/Microsoft) para o usuario atual, apenas para os
     * provedores com credenciais configuradas. Guardado para nunca quebrar a pagina.
     */
    private function calendarIntegrations(?User $user): array
    {
        if (!$user || !Schema::hasTable('calendar_connections')) {
            return [];
        }

        $providers = app(CalendarProviders::class)->configured();
        if ($providers === []) {
            return [];
        }

        $existing = CalendarConnection::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('provider');

        $integrations = [];
        foreach ($providers as $key => $provider) {
            $integrations[] = [
                'key' => $key,
                'label' => $provider->label(),
                'connection' => $existing->get($key),
            ];
        }

        return $integrations;
    }

    public function feed(string $token): Response
    {
        abort_unless(Schema::hasColumn('users', 'calendar_feed_token'), 404);

        $user = User::query()->where('calendar_feed_token', $token)->first();
        abort_unless($user, 404);

        $events = AgendaEvent::query()
            ->where('status', '!=', 'cancelado')
            ->forUser((int) $user->id)
            ->whereBetween('start_at', [now()->subDays(30)->startOfDay(), now()->addDays(180)->endOfDay()])
            ->orderBy('start_at')
            ->get();

        $ics = IcsBuilder::calendar($events, 'Agenda Ancora - ' . $user->name);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="agenda.ics"',
        ]);
    }

    public function downloadIcs(AgendaEvent $evento): Response
    {
        $ics = IcsBuilder::singleEvent($evento);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="compromisso-' . $evento->id . '.ics"',
        ]);
    }

    private function feedTokenForUser(User $user): string
    {
        $token = trim((string) ($user->calendar_feed_token ?? ''));
        if ($token === '') {
            $token = Str::lower(Str::random(48));
            $user->forceFill(['calendar_feed_token' => $token])->save();
        }

        return $token;
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => trim((string) $request->input('type', '')),
            'status' => trim((string) $request->input('status', '')),
            'responsible_user_id' => (int) $request->integer('responsible_user_id') ?: null,
            'from' => trim((string) $request->input('from', '')),
            'to' => trim((string) $request->input('to', '')),
            'fatal_only' => $request->boolean('fatal_only'),
            'overdue_only' => $request->boolean('overdue_only'),
        ];

        $query = AgendaEvent::query()->with(['responsible', 'process', 'client']);
        $this->applyFilters($query, $filters);

        $events = $query->orderByRaw("CASE WHEN status = 'aberto' THEN 0 ELSE 1 END")
            ->orderBy('start_at')
            ->paginate(20)
            ->withQueryString();

        return view('pages.agenda.index', array_merge($this->formOptions(), [
            'title' => 'Agenda - lista',
            'events' => $events,
            'filters' => $filters,
        ]));
    }

    public function create(Request $request): View
    {
        $startAt = now()->format('Y-m-d\TH:i');
        if ($request->filled('start')) {
            try {
                $parsed = Carbon::parse((string) $request->query('start'));
                // Datas sem hora (clique no mes/dia inteiro) assumem 09:00.
                $startAt = ($parsed->format('H:i') === '00:00' && strlen((string) $request->query('start')) <= 10)
                    ? $parsed->setTime(9, 0)->format('Y-m-d\TH:i')
                    : $parsed->format('Y-m-d\TH:i');
            } catch (\Throwable) {
                // ignora start invalido
            }
        }

        $draft = [
            'type' => 'compromisso',
            'status' => 'aberto',
            'start_at' => $startAt,
        ];

        $process = $request->filled('process')
            ? ProcessCase::query()->find((int) $request->input('process'))
            : null;

        if ($process) {
            $draft = array_merge($draft, [
                'type' => 'prazo',
                'is_fatal' => true,
                'process_id' => $process->id,
                'client_id' => $process->client_entity_id ?? null,
            ]);
        }

        return view('pages.agenda.form', array_merge($this->formOptions(), [
            'title' => 'Novo compromisso',
            'mode' => 'create',
            'item' => null,
            'draft' => $draft,
            'parentProcess' => $process,
            'selectedParticipants' => [],
        ]));
    }

    public function store(StoreAgendaEventRequest $request): RedirectResponse|JsonResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $payload = $this->normalizedPayload($request);
        $participants = collect((array) $request->input('participants', []))->map(fn ($id) => (int) $id)->filter()->unique()->all();
        $reminderMinutes = $this->collectReminderMinutes($request);

        $starts = $this->expandRecurrence($payload);
        $duration = $this->durationMinutes($payload);
        $hasEnd = !empty($payload['end_at']);
        $group = count($starts) > 1 ? (string) Str::uuid() : null;
        $first = null;

        foreach ($starts as $start) {
            $event = AgendaEvent::query()->create(array_merge($payload, [
                'start_at' => $start->format('Y-m-d H:i:s'),
                'end_at' => $hasEnd ? $start->copy()->addMinutes($duration)->format('Y-m-d H:i:s') : null,
                'recurrence_group' => $group,
                // Sem responsavel explicito, assume o criador para que o push (Agenda -> Google)
                // va para o calendario de quem criou o compromisso.
                'responsible_user_id' => $payload['responsible_user_id'] ?? $user->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]));

            if ($participants !== []) {
                $event->participants()->sync($participants);
            }

            $this->syncReminders($event, $reminderMinutes);

            SyncAgendaEventToCalendarsJob::dispatch($event->id, 'upsert');
            $first ??= $event;
        }

        $message = count($starts) > 1
            ? count($starts) . ' compromissos criados na recorrencia.'
            : 'Compromisso criado com sucesso.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        // Criacao via modal volta para o calendario; via pagina, vai para o detalhe.
        return $request->boolean('_modal')
            ? redirect()->route('agenda.calendar')->with('success', $message)
            : redirect()->route('agenda.show', $first)->with('success', $message);
    }

    /**
     * Expande a recorrencia em datas de inicio. Sem recorrencia (ou sem data limite), retorna so a inicial.
     */
    private function expandRecurrence(array $payload): array
    {
        $start = Carbon::parse($payload['start_at']);
        $recurrence = (string) ($payload['recurrence'] ?? '');
        $until = !empty($payload['recurrence_until']) ? Carbon::parse($payload['recurrence_until'])->endOfDay() : null;

        if ($recurrence === '' || !$until || $until->lt($start)) {
            return [$start];
        }

        $dates = [];
        $cursor = $start->copy();
        $guard = 0;
        while ($cursor->lte($until) && $guard < 366) {
            $dates[] = $cursor->copy();
            $cursor = match ($recurrence) {
                'daily' => $cursor->copy()->addDay(),
                'weekly' => $cursor->copy()->addWeek(),
                'biweekly' => $cursor->copy()->addWeeks(2),
                'monthly' => $cursor->copy()->addMonthNoOverflow(),
                default => $until->copy()->addDay(), // encerra
            };
            $guard++;
        }

        return $dates ?: [$start];
    }

    private function durationMinutes(array $payload): int
    {
        if (empty($payload['end_at'])) {
            return 0;
        }

        return (int) Carbon::parse($payload['start_at'])->diffInMinutes(Carbon::parse($payload['end_at']));
    }

    public function show(Request $request, AgendaEvent $evento): View
    {
        $evento->load(['responsible', 'requester', 'process', 'demand', 'client', 'contract', 'completer', 'participants', 'attachments.uploader']);

        $data = [
            'title' => $evento->title,
            'item' => $evento,
            'typeLabels' => AgendaCatalog::types(),
            'statusLabels' => AgendaCatalog::statuses(),
        ];

        // ?modal=1 (clique no calendario) devolve apenas o fragmento para injetar no modal.
        return view($request->boolean('modal') ? 'pages.agenda.partials._detail' : 'pages.agenda.show', $data);
    }

    public function edit(Request $request, AgendaEvent $evento): View
    {
        $evento->load('participants');

        $data = array_merge($this->formOptions(), [
            'title' => 'Editar compromisso',
            'mode' => 'edit',
            'item' => $evento,
            'draft' => $evento->toArray(),
            'parentProcess' => null,
            'selectedParticipants' => $evento->participants->pluck('id')->all(),
        ]);

        return $request->boolean('modal')
            ? view('pages.agenda.partials._form', array_merge($data, ['inModal' => true]))
            : view('pages.agenda.form', $data);
    }

    public function update(UpdateAgendaEventRequest $request, AgendaEvent $evento): RedirectResponse|JsonResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $evento->update(array_merge($this->normalizedPayload($request), [
            'updated_by' => $user->id,
        ]));

        $participants = collect((array) $request->input('participants', []))->map(fn ($id) => (int) $id)->filter()->unique()->all();
        $evento->participants()->sync($participants);

        $this->syncReminders($evento, $this->collectReminderMinutes($request));

        SyncAgendaEventToCalendarsJob::dispatch($evento->id, 'upsert');

        $message = 'Compromisso atualizado com sucesso.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()
            ->route($request->boolean('_modal') ? 'agenda.calendar' : 'agenda.show', $request->boolean('_modal') ? [] : ['evento' => $evento])
            ->with('success', $message);
    }

    public function complete(Request $request, AgendaEvent $evento): RedirectResponse|JsonResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if ($evento->status !== 'concluido') {
            $evento->update([
                'status' => 'concluido',
                'completed_at' => now(),
                'completed_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            SyncAgendaEventToCalendarsJob::dispatch($evento->id, 'upsert');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Compromisso concluido.']);
        }

        return back()->with('success', 'Compromisso concluido.');
    }

    public function destroy(Request $request, AgendaEvent $evento): RedirectResponse|JsonResponse
    {
        $eventId = (int) $evento->id;
        $evento->delete();

        SyncAgendaEventToCalendarsJob::dispatch($eventId, 'delete');

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Compromisso removido.']);
        }

        return redirect()->route('agenda.index')->with('success', 'Compromisso removido.');
    }

    public function uploadAttachment(Request $request, AgendaEvent $evento): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        $file = $request->file('file');
        $storedName = 'agenda-' . $evento->id . '-' . now()->format('YmdHis') . '-' . Str::random(8) . '.' . strtolower((string) ($file->getClientOriginalExtension() ?: 'bin'));
        $relativePath = 'agenda/' . $evento->id . '/' . $storedName;

        Storage::disk('public')->putFileAs('agenda/' . $evento->id, $file, $storedName);

        AgendaEventAttachment::query()->create([
            'agenda_event_id' => $evento->id,
            'original_name' => (string) $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'relative_path' => $relativePath,
            'mime_type' => (string) ($file->getClientMimeType() ?: ''),
            'file_size' => (int) $file->getSize(),
            'uploaded_by' => $user->id,
        ]);

        return back()->with('success', 'Anexo enviado com sucesso.');
    }

    public function downloadAttachment(AgendaEvent $evento, AgendaEventAttachment $attachment): BinaryFileResponse
    {
        abort_unless((int) $attachment->agenda_event_id === (int) $evento->id, 404);

        $path = Storage::disk('public')->path($attachment->relative_path);
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->original_name);
    }

    public function deleteAttachment(Request $request, AgendaEvent $evento, AgendaEventAttachment $attachment): RedirectResponse
    {
        abort_unless((int) $attachment->agenda_event_id === (int) $evento->id, 404);

        Storage::disk('public')->delete($attachment->relative_path);
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }

    private function normalizedPayload(Request $request): array
    {
        $data = $request->validated();

        return [
            'title' => trim((string) $data['title']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'type' => $data['type'],
            'status' => $data['status'] ?? 'aberto',
            'priority' => $data['priority'] ?? null,
            'color' => $request->boolean('apply_color') ? \App\Support\ColorContrast::normalizeHex($data['color'] ?? null) : null,
            'is_fatal' => $request->boolean('is_fatal'),
            'all_day' => $request->boolean('all_day'),
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'] ?? null,
            'recurrence' => trim((string) ($data['recurrence'] ?? '')) ?: null,
            'recurrence_until' => $data['recurrence_until'] ?? null,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            // reminder_minutes legado = menor lembrete (compatibilidade ICS); os lembretes
            // completos ficam em agenda_event_reminders via syncReminders().
            'reminder_minutes' => $this->collectReminderMinutes($request)[0] ?? null,
            'remind_email' => $request->boolean('remind_email'),
            'remind_whatsapp' => $request->boolean('remind_whatsapp'),
            'copy_enabled' => $request->boolean('copy_enabled'),
            'copy_name' => $request->boolean('copy_enabled') ? (trim((string) ($data['copy_name'] ?? '')) ?: null) : null,
            'copy_phone' => $request->boolean('copy_enabled') ? (preg_replace('/\D+/', '', (string) ($data['copy_phone'] ?? '')) ?: null) : null,
            'copy_email' => $request->boolean('copy_enabled') ? (trim((string) ($data['copy_email'] ?? '')) ?: null) : null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'requester_user_id' => $data['requester_user_id'] ?? null,
            'process_id' => $data['process_id'] ?? null,
            'demand_id' => $data['demand_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
        ];
    }

    /**
     * Lista de antecedencias (minutos) dos lembretes, deduplicada e ordenada.
     * Aceita o campo novo reminders[] e o legado reminder_minutes.
     *
     * @return array<int, int>
     */
    private function collectReminderMinutes(Request $request): array
    {
        $list = collect((array) $request->input('reminders', []))
            ->map(fn ($m) => (int) $m)
            ->filter(fn ($m) => $m > 0);

        $legacy = (int) $request->integer('reminder_minutes');
        if ($legacy > 0) {
            $list->push($legacy);
        }

        return $list->unique()->sort()->values()->all();
    }

    /**
     * Sincroniza as linhas de lembrete do evento, preservando o sent_at dos que continuam.
     *
     * @param array<int, int> $minutes
     */
    private function syncReminders(AgendaEvent $event, array $minutes): void
    {
        if (!Schema::hasTable('agenda_event_reminders')) {
            return;
        }

        $existing = $event->reminders()->pluck('minutes_before')->all();

        // Remove os que sairam.
        $event->reminders()->whereNotIn('minutes_before', $minutes ?: [-1])->delete();

        // Cria apenas os novos (mantem sent_at dos preservados).
        foreach (array_diff($minutes, $existing) as $m) {
            $event->reminders()->create(['minutes_before' => (int) $m]);
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%");
            });
        }
        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['responsible_user_id']) {
            $query->where('responsible_user_id', $filters['responsible_user_id']);
        }
        if ($filters['from'] !== '') {
            $query->whereDate('start_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('start_at', '<=', $filters['to']);
        }
        if ($filters['fatal_only']) {
            $query->where('is_fatal', true);
        }
        if ($filters['overdue_only']) {
            $query->where('status', 'aberto')->where('start_at', '<', now());
        }
    }

    private function referenceMonth(Request $request): Carbon
    {
        $raw = trim((string) $request->input('month', ''));
        if ($raw !== '') {
            try {
                return Carbon::createFromFormat('Y-m', $raw)->startOfMonth();
            } catch (\Throwable) {
                // ignore and fall back to current month
            }
        }

        return now()->startOfMonth();
    }

    private function formOptions(): array
    {
        return [
            'typeOptions' => AgendaCatalog::types(),
            'statusOptions' => AgendaCatalog::statuses(),
            'priorityOptions' => AgendaCatalog::priorities(),
            'reminderOptions' => AgendaCatalog::reminderOptions(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'processes' => ProcessCase::query()->orderByDesc('id')->limit(300)->get(['id', 'process_number', 'client_name_snapshot']),
            'demands' => Demand::query()->orderByDesc('id')->limit(300)->get(['id', 'protocol', 'subject']),
            'clients' => ClientEntity::query()->where('is_active', true)->orderBy('display_name')->get(['id', 'display_name']),
            'contracts' => Contract::query()->orderByDesc('id')->limit(300)->get(['id', 'code', 'title']),
        ];
    }
}
