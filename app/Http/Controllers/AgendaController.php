<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgendaEventRequest;
use App\Http\Requests\UpdateAgendaEventRequest;
use App\Jobs\SyncAgendaEventToCalendarsJob;
use App\Models\AgendaEvent;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

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

        return view('pages.agenda.calendar', [
            'title' => 'Agenda',
            'reference' => $reference,
            'weeks' => $weeks,
            'prevMonth' => $reference->copy()->subMonthNoOverflow(),
            'nextMonth' => $reference->copy()->addMonthNoOverflow(),
            'typeLabels' => AgendaCatalog::types(),
            'feedUrl' => $feedUrl,
            'calendarIntegrations' => $this->calendarIntegrations($user),
        ]);
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
        $draft = [
            'type' => 'compromisso',
            'status' => 'aberto',
            'start_at' => now()->format('Y-m-d\TH:i'),
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
        ]));
    }

    public function store(StoreAgendaEventRequest $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $event = AgendaEvent::query()->create(array_merge($this->normalizedPayload($request), [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]));

        SyncAgendaEventToCalendarsJob::dispatch($event->id, 'upsert');

        return redirect()->route('agenda.show', $event)->with('success', 'Compromisso criado com sucesso.');
    }

    public function show(AgendaEvent $evento): View
    {
        $evento->load(['responsible', 'requester', 'process', 'demand', 'client', 'contract', 'completer']);

        return view('pages.agenda.show', [
            'title' => $evento->title,
            'item' => $evento,
            'typeLabels' => AgendaCatalog::types(),
            'statusLabels' => AgendaCatalog::statuses(),
        ]);
    }

    public function edit(AgendaEvent $evento): View
    {
        return view('pages.agenda.form', array_merge($this->formOptions(), [
            'title' => 'Editar compromisso',
            'mode' => 'edit',
            'item' => $evento,
            'draft' => $evento->toArray(),
            'parentProcess' => null,
        ]));
    }

    public function update(UpdateAgendaEventRequest $request, AgendaEvent $evento): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $evento->update(array_merge($this->normalizedPayload($request), [
            'updated_by' => $user->id,
        ]));

        SyncAgendaEventToCalendarsJob::dispatch($evento->id, 'upsert');

        return redirect()->route('agenda.show', $evento)->with('success', 'Compromisso atualizado com sucesso.');
    }

    public function complete(Request $request, AgendaEvent $evento): RedirectResponse
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

        return back()->with('success', 'Compromisso concluido.');
    }

    public function destroy(AgendaEvent $evento): RedirectResponse
    {
        $eventId = (int) $evento->id;
        $evento->delete();

        SyncAgendaEventToCalendarsJob::dispatch($eventId, 'delete');

        return redirect()->route('agenda.index')->with('success', 'Compromisso removido.');
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
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'reminder_minutes' => ($data['reminder_minutes'] ?? null) !== null && $data['reminder_minutes'] !== ''
                ? (int) $data['reminder_minutes']
                : null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'requester_user_id' => $data['requester_user_id'] ?? null,
            'process_id' => $data['process_id'] ?? null,
            'demand_id' => $data['demand_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null,
        ];
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
