<?php

namespace App\Http\Controllers;

use App\Models\ClientEntity;
use App\Models\ClientCondominium;
use App\Models\ProcessCase;
use App\Models\ProcessCaseAttachment;
use App\Models\ProcessCaseOption;
use App\Models\ProcessCasePhase;
use App\Services\ProcessDataJudService;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProcessController extends Controller
{
    private array $optionLabels = [
        'status' => 'Status',
        'action_type' => 'Tipo de acao',
        'process_type' => 'Tipo de processo',
        'client_position' => 'Posicao do cliente',
        'adverse_position' => 'Posicao do adverso',
        'nature' => 'Natureza',
        'win_probability' => 'Possibilidade de ganho',
        'closure_type' => 'Tipo de encerramento',
        'datajud_court' => 'Tribunal DataJud',
    ];

    public function dashboard(Request $request): View
    {
        $year = max(2024, (int) $request->integer('year', now()->year));
        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $monthStart = now()->copy()->startOfMonth();
        $monthEnd = now()->copy()->endOfMonth();

        $casesQuery = $this->visibleProcessQuery($request)
            ->with([
                'statusOption',
                'actionTypeOption',
                'processTypeOption',
                'natureOption',
                'winProbabilityOption',
            ])
            ->withCount(['phases', 'attachments'])
            ->withMax('phases', 'phase_date');

        $cases = $casesQuery->get();

        $phaseYearQuery = $this->visibleProcessPhaseQuery($request)
            ->where(function ($query) use ($year) {
                $query->whereYear('phase_date', $year)
                    ->orWhere(function ($fallback) use ($year) {
                        $fallback->whereNull('phase_date')->whereYear('created_at', $year);
                    });
            });

        $phasesForYear = $phaseYearQuery->get();
        $phaseMonthCount = $this->visibleProcessPhaseQuery($request)
            ->where(function ($query) use ($monthStart, $monthEnd) {
                $query->whereBetween('phase_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->orWhere(function ($fallback) use ($monthStart, $monthEnd) {
                        $fallback->whereNull('phase_date')
                            ->whereBetween('created_at', [$monthStart, $monthEnd]);
                    });
            })
            ->count();

        $caseCounts = array_fill(0, 12, 0);
        foreach ($cases as $case) {
            $referenceDate = $this->processReferenceDate($case);
            if ($referenceDate && (int) $referenceDate->year === $year) {
                $caseCounts[$referenceDate->month - 1]++;
            }
        }

        $movementCounts = array_fill(0, 12, 0);
        $manualMovementCounts = array_fill(0, 12, 0);
        $datajudMovementCounts = array_fill(0, 12, 0);
        foreach ($phasesForYear as $phase) {
            $referenceDate = $this->phaseReferenceDate($phase);
            if (!$referenceDate || (int) $referenceDate->year !== $year) {
                continue;
            }

            $index = $referenceDate->month - 1;
            $movementCounts[$index]++;
            if ($phase->source === 'datajud') {
                $datajudMovementCounts[$index]++;
            } else {
                $manualMovementCounts[$index]++;
            }
        }

        $currentMonthCases = $cases->filter(function (ProcessCase $case) use ($monthStart, $monthEnd) {
            $referenceDate = $this->processReferenceDate($case);
            return $referenceDate && $referenceDate->between($monthStart, $monthEnd);
        });

        $yearCases = $cases->filter(function (ProcessCase $case) use ($year) {
            $referenceDate = $this->processReferenceDate($case);
            return $referenceDate && (int) $referenceDate->year === $year;
        });

        $attentionRows = $cases
            ->filter(fn (ProcessCase $case) => !$case->closed_at)
            ->map(function (ProcessCase $case) {
                $lastMovementDate = $this->lastMovementDate($case);

                return [
                    'case' => $case,
                    'last_movement_date' => $lastMovementDate,
                    'days_without_movement' => $lastMovementDate ? $lastMovementDate->diffInDays(now()) : null,
                ];
            })
            ->sortBy(fn (array $row) => $row['last_movement_date']?->timestamp ?? 0);

        $attentionCases = $attentionRows->take(6)->values();

        $summary = [
            'total' => $cases->count(),
            'year_total' => $yearCases->count(),
            'month_total' => $currentMonthCases->count(),
            'active' => $cases->filter(fn (ProcessCase $case) => !$case->closed_at)->count(),
            'closed' => $cases->filter(fn (ProcessCase $case) => (bool) $case->closed_at)->count(),
            'private' => $cases->where('is_private', true)->count(),
            'datajud_ready' => $cases->filter(fn (ProcessCase $case) => filled($case->process_number) && filled($case->datajud_court))->count(),
            'datajud_synced' => $cases->filter(fn (ProcessCase $case) => (bool) $case->last_datajud_sync_at)->count(),
            'movements_year' => $phasesForYear->count(),
            'movements_month' => $phaseMonthCount,
            'manual_movements_year' => $phasesForYear->where('source', 'manual')->count(),
            'datajud_movements_year' => $phasesForYear->where('source', 'datajud')->count(),
            'stale_90' => $attentionRows->filter(fn (array $row) => ($row['days_without_movement'] ?? 0) >= 90)->count(),
            'claim_amount' => $cases->sum(fn (ProcessCase $case) => (float) $case->claim_amount),
            'provisioned_amount' => $cases->sum(fn (ProcessCase $case) => (float) $case->provisioned_amount),
            'court_paid_amount' => $cases->sum(fn (ProcessCase $case) => (float) $case->court_paid_amount),
            'process_cost_amount' => $cases->sum(fn (ProcessCase $case) => (float) $case->process_cost_amount),
            'sentence_amount' => $cases->sum(fn (ProcessCase $case) => (float) $case->sentence_amount),
            'month_label' => $monthLabels[now()->month - 1] . '/' . now()->year,
        ];

        $latestCases = $this->visibleProcessQuery($request)
            ->with(['statusOption', 'processTypeOption'])
            ->withCount(['phases', 'attachments'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $latestPhases = $this->visibleProcessPhaseQuery($request)
            ->with(['processCase.statusOption'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        $years = $this->visibleProcessQuery($request)
            ->selectRaw('YEAR(COALESCE(opened_at, created_at)) as year_number')
            ->groupByRaw('YEAR(COALESCE(opened_at, created_at))')
            ->orderByDesc('year_number')
            ->pluck('year_number')
            ->filter()
            ->values();

        return view('pages.processos.dashboard', [
            'title' => 'Dashboard de Processos',
            'year' => $year,
            'years' => $years,
            'summary' => $summary,
            'statusDistribution' => $this->optionDistribution($cases, 'statusOption', 'Sem status'),
            'typeDistribution' => $this->optionDistribution($cases, 'processTypeOption', 'Sem tipo'),
            'natureDistribution' => $this->optionDistribution($cases, 'natureOption', 'Sem natureza'),
            'latestCases' => $latestCases,
            'latestPhases' => $latestPhases,
            'attentionCases' => $attentionCases,
            'chartData' => [
                'labels' => $monthLabels,
                'caseCounts' => $caseCounts,
                'movementCounts' => $movementCounts,
                'manualMovementCounts' => $manualMovementCounts,
                'datajudMovementCounts' => $datajudMovementCounts,
                'financialLabels' => ['Valor da causa', 'Provisionado', 'Pago em juizo', 'Custos', 'Sentenca'],
                'financialTotals' => [
                    round((float) $summary['claim_amount'], 2),
                    round((float) $summary['provisioned_amount'], 2),
                    round((float) $summary['court_paid_amount'], 2),
                    round((float) $summary['process_cost_amount'], 2),
                    round((float) $summary['sentence_amount'], 2),
                ],
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status_option_id' => (int) $request->integer('status_option_id') ?: null,
            'process_type_option_id' => (int) $request->integer('process_type_option_id') ?: null,
            'datajud_court' => trim((string) $request->input('datajud_court', '')),
            'private' => trim((string) $request->input('private', '')),
        ];
        $sort = in_array($request->input('sort'), ['process_number', 'opened_at', 'client', 'adverse', 'status', 'type', 'responsible'], true)
            ? (string) $request->input('sort')
            : 'opened_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $query = ProcessCase::query()
            ->with(['statusOption', 'processTypeOption', 'client', 'adverse', 'creator'])
            ->withCount(['phases', 'attachments'])
            ->select('process_cases.*');

        $this->applyVisibility($query, $request);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('process_number', 'like', "%{$q}%")
                    ->orWhere('client_name_snapshot', 'like', "%{$q}%")
                    ->orWhere('adverse_name', 'like', "%{$q}%")
                    ->orWhere('responsible_lawyer', 'like', "%{$q}%");
            });
        }
        if ($filters['status_option_id']) {
            $query->where('status_option_id', $filters['status_option_id']);
        }
        if ($filters['process_type_option_id']) {
            $query->where('process_type_option_id', $filters['process_type_option_id']);
        }
        if ($filters['datajud_court'] !== '') {
            $query->where('datajud_court', $filters['datajud_court']);
        }
        if ($filters['private'] === '1') {
            $query->where('is_private', true);
        } elseif ($filters['private'] === '0') {
            $query->where('is_private', false);
        }

        match ($sort) {
            'process_number' => $query->orderBy('process_number', $direction),
            'client' => $query->orderBy('client_name_snapshot', $direction),
            'adverse' => $query->orderBy('adverse_name', $direction),
            'responsible' => $query->orderBy('responsible_lawyer', $direction),
            'status' => $query
                ->leftJoin('process_case_options as status_sort', 'status_sort.id', '=', 'process_cases.status_option_id')
                ->orderBy('status_sort.name', $direction),
            'type' => $query
                ->leftJoin('process_case_options as type_sort', 'type_sort.id', '=', 'process_cases.process_type_option_id')
                ->orderBy('type_sort.name', $direction),
            default => $query->orderBy('opened_at', $direction)->orderByDesc('id'),
        };

        $items = $query->paginate(15)->withQueryString();
        $options = $this->optionsForForms();

        return view('pages.processos.index', [
            'title' => 'Processos',
            'items' => $items,
            'filters' => $filters,
            'options' => $options,
            'sortState' => ['sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function create(): View
    {
        return view('pages.processos.form', [
            'title' => 'Novo processo',
            'case' => null,
            'action' => route('processos.store'),
            'formData' => $this->formData(),
            'options' => $this->optionsForForms(),
            'entities' => $this->entitiesForLookup(),
            'condominiums' => $this->condominiumsForLookup(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        $case = DB::transaction(function () use ($request, $user) {
            return ProcessCase::query()->create($this->payloadFromRequest($request, null) + [
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
        });

        if ($request->input('_next') === 'phase') {
            return redirect()->route('processos.show', ['processo' => $case, 'tab' => 'fases', 'open_phase' => 1])
                ->with('success', 'Processo salvo. Cadastre a nova fase.');
        }

        return redirect()->route('processos.show', $case)->with('success', 'Processo salvo.');
    }

    public function show(Request $request, ProcessCase $processo): View
    {
        $this->authorizeProcessAccess($request, $processo);

        $processo->load([
            'statusOption',
            'actionTypeOption',
            'processTypeOption',
            'client',
            'clientCondominium',
            'adverse',
            'clientPositionOption',
            'adversePositionOption',
            'natureOption',
            'winProbabilityOption',
            'closureTypeOption',
            'creator',
            'phases.creator',
            'phases.attachments',
            'attachments.uploader',
        ]);

        return view('pages.processos.show', [
            'title' => $processo->process_number ?: 'Processo #' . $processo->id,
            'case' => $processo,
            'activeTab' => in_array($request->query('tab'), ['fases', 'anexos'], true) ? $request->query('tab') : 'resumo',
            'openPhase' => $request->boolean('open_phase'),
        ]);
    }

    public function edit(Request $request, ProcessCase $processo): View
    {
        $this->authorizeProcessAccess($request, $processo);

        return view('pages.processos.form', [
            'title' => 'Editar processo',
            'case' => $processo,
            'action' => route('processos.update', $processo),
            'formData' => $this->formData($processo),
            'options' => $this->optionsForForms(),
            'entities' => $this->entitiesForLookup(),
            'condominiums' => $this->condominiumsForLookup(),
        ]);
    }

    public function update(Request $request, ProcessCase $processo): RedirectResponse
    {
        $this->authorizeProcessAccess($request, $processo);
        $user = AncoraAuth::user($request);

        DB::transaction(function () use ($request, $processo, $user) {
            $processo->update($this->payloadFromRequest($request, $processo) + [
                'updated_by' => $user?->id,
            ]);
        });

        if ($request->input('_next') === 'phase') {
            return redirect()->route('processos.show', ['processo' => $processo, 'tab' => 'fases', 'open_phase' => 1])
                ->with('success', 'Processo atualizado. Cadastre a nova fase.');
        }

        return redirect()->route('processos.show', $processo)->with('success', 'Processo atualizado.');
    }

    public function destroy(Request $request, ProcessCase $processo): RedirectResponse
    {
        $this->authorizeProcessAccess($request, $processo);

        foreach ($processo->attachments as $attachment) {
            $this->deletePhysicalAttachment($attachment);
        }

        $processo->delete();

        return redirect()->route('processos.index')->with('success', 'Processo excluido.');
    }

    public function storePhase(Request $request, ProcessCase $processo): RedirectResponse
    {
        $this->authorizeProcessAccess($request, $processo);
        $user = AncoraAuth::user($request);
        $validated = $request->validate([
            'phase_date' => ['required', 'date'],
            'phase_time' => ['nullable', 'date_format:H:i'],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'legal_opinion' => ['nullable', 'string'],
            'conference' => ['nullable', 'string'],
        ]);

        $phase = ProcessCasePhase::query()->create([
            'process_case_id' => $processo->id,
            'phase_date' => $validated['phase_date'],
            'phase_time' => $validated['phase_time'] ?? null,
            'description' => $validated['description'],
            'is_private' => $request->boolean('is_private'),
            'is_reviewed' => $request->boolean('is_reviewed'),
            'notes' => $validated['notes'] ?? null,
            'legal_opinion' => $validated['legal_opinion'] ?? null,
            'conference' => $validated['conference'] ?? null,
            'source' => 'manual',
            'created_by' => $user?->id,
        ]);

        $uploaded = $this->storeAttachments($request, $processo, $phase, 'andamento');

        return redirect()->route('processos.show', ['processo' => $processo, 'tab' => 'fases'])
            ->with('success', 'Fase cadastrada.' . ($uploaded > 0 ? " {$uploaded} arquivo(s) anexado(s)." : ''));
    }

    public function uploadAttachment(Request $request, ProcessCase $processo): RedirectResponse
    {
        $this->authorizeProcessAccess($request, $processo);
        $uploaded = $this->storeAttachments($request, $processo, null, trim((string) $request->input('file_role', 'documento')) ?: 'documento');

        if ($uploaded === 0) {
            return back()->with('error', 'Selecione pelo menos um arquivo valido.');
        }

        return redirect()->route('processos.show', ['processo' => $processo, 'tab' => 'anexos'])
            ->with('success', "{$uploaded} arquivo(s) anexado(s).");
    }

    public function downloadAttachment(Request $request, ProcessCase $processo, ProcessCaseAttachment $attachment): BinaryFileResponse
    {
        $this->authorizeProcessAccess($request, $processo);
        abort_if($attachment->process_case_id !== $processo->id, 404);
        $path = public_path(ltrim((string) $attachment->relative_path, '/'));
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->original_name);
    }

    public function deleteAttachment(Request $request, ProcessCase $processo, ProcessCaseAttachment $attachment): RedirectResponse
    {
        $this->authorizeProcessAccess($request, $processo);
        abort_if($attachment->process_case_id !== $processo->id, 404);

        $this->deletePhysicalAttachment($attachment);
        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }

    public function syncDataJud(Request $request, ProcessCase $processo, ProcessDataJudService $service): RedirectResponse
    {
        $this->authorizeProcessAccess($request, $processo);
        $result = $service->syncCase($processo);

        if (!empty($result['error'])) {
            return back()->with('error', 'DataJud: ' . $result['error']);
        }

        return redirect()->route('processos.show', ['processo' => $processo, 'tab' => 'fases'])
            ->with('success', 'DataJud sincronizado. Movimentos novos: ' . ($result['created'] ?? 0) . ' · movimentos atualizados: ' . ($result['refreshed'] ?? 0) . '.');
    }

    private function payloadFromRequest(Request $request, ?ProcessCase $current): array
    {
        $validated = $request->validate([
            'responsible_lawyer' => ['nullable', 'string', 'max:160'],
            'opened_at' => ['nullable', 'date'],
            'process_number' => ['nullable', 'string', 'max:80'],
            'datajud_court' => ['nullable', 'string', 'max:80'],
            'client_name' => ['nullable', 'string', 'max:190'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'adverse_name' => ['nullable', 'string', 'max:190'],
            'client_lawyer' => ['nullable', 'string', 'max:160'],
            'adverse_lawyer' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
            'claim_amount_date' => ['nullable', 'date'],
            'provisioned_amount_date' => ['nullable', 'date'],
            'court_paid_amount_date' => ['nullable', 'date'],
            'process_cost_amount_date' => ['nullable', 'date'],
            'sentence_amount_date' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'closed_by' => ['nullable', 'string', 'max:160'],
            'closure_notes' => ['nullable', 'string'],
        ]);

        $client = $this->resolveEntity((string) $request->input('client_name', ''));
        $adverse = $this->resolveEntity((string) $request->input('adverse_name', ''));

        return [
            'responsible_lawyer' => $validated['responsible_lawyer'] ?? null,
            'opened_at' => $validated['opened_at'] ?? null,
            'process_number' => $this->formatProcessNumber((string) ($validated['process_number'] ?? '')),
            'datajud_court' => $validated['datajud_court'] ?? null,
            'status_option_id' => $this->optionId($request, 'status_option_id', 'status'),
            'action_type_option_id' => $this->optionId($request, 'action_type_option_id', 'action_type'),
            'process_type_option_id' => $this->optionId($request, 'process_type_option_id', 'process_type'),
            'client_entity_id' => $client?->id,
            'client_condominium_id' => $validated['client_condominium_id'] ?? null,
            'client_name_snapshot' => $client?->display_name ?: ($validated['client_name'] ?? null),
            'adverse_entity_id' => $adverse?->id,
            'adverse_name' => $adverse?->display_name ?: ($validated['adverse_name'] ?? null),
            'client_position_option_id' => $this->optionId($request, 'client_position_option_id', 'client_position'),
            'adverse_position_option_id' => $this->optionId($request, 'adverse_position_option_id', 'adverse_position'),
            'client_lawyer' => $validated['client_lawyer'] ?? null,
            'adverse_lawyer' => $validated['adverse_lawyer'] ?? null,
            'nature_option_id' => $this->optionId($request, 'nature_option_id', 'nature'),
            'is_private' => $request->boolean('is_private'),
            'claim_amount' => $this->money($request->input('claim_amount')),
            'claim_amount_date' => $validated['claim_amount_date'] ?? null,
            'provisioned_amount' => $this->money($request->input('provisioned_amount')),
            'provisioned_amount_date' => $validated['provisioned_amount_date'] ?? null,
            'court_paid_amount' => $this->money($request->input('court_paid_amount')),
            'court_paid_amount_date' => $validated['court_paid_amount_date'] ?? null,
            'process_cost_amount' => $this->money($request->input('process_cost_amount')),
            'process_cost_amount_date' => $validated['process_cost_amount_date'] ?? null,
            'sentence_amount' => $this->money($request->input('sentence_amount')),
            'sentence_amount_date' => $validated['sentence_amount_date'] ?? null,
            'win_probability_option_id' => $this->optionId($request, 'win_probability_option_id', 'win_probability'),
            'notes' => $validated['notes'] ?? null,
            'closed_at' => $validated['closed_at'] ?? null,
            'closed_by' => $validated['closed_by'] ?? null,
            'closure_type_option_id' => $this->optionId($request, 'closure_type_option_id', 'closure_type'),
            'closure_notes' => $validated['closure_notes'] ?? null,
        ];
    }

    private function formData(?ProcessCase $case = null): array
    {
        return [
            'responsible_lawyer' => old('responsible_lawyer', $case?->responsible_lawyer),
            'opened_at' => old('opened_at', $case?->opened_at?->format('Y-m-d')),
            'process_number' => old('process_number', $case?->process_number),
            'datajud_court' => old('datajud_court', $case?->datajud_court),
            'status_option_id' => old('status_option_id', $case?->status_option_id),
            'action_type_option_id' => old('action_type_option_id', $case?->action_type_option_id),
            'process_type_option_id' => old('process_type_option_id', $case?->process_type_option_id),
            'client_name' => old('client_name', $case?->client_name_snapshot),
            'client_condominium_id' => old('client_condominium_id', $case?->client_condominium_id),
            'adverse_name' => old('adverse_name', $case?->adverse_name),
            'client_position_option_id' => old('client_position_option_id', $case?->client_position_option_id),
            'adverse_position_option_id' => old('adverse_position_option_id', $case?->adverse_position_option_id),
            'client_lawyer' => old('client_lawyer', $case?->client_lawyer),
            'adverse_lawyer' => old('adverse_lawyer', $case?->adverse_lawyer),
            'nature_option_id' => old('nature_option_id', $case?->nature_option_id),
            'is_private' => old('is_private', $case?->is_private),
            'claim_amount' => old('claim_amount', $this->moneyForInput($case?->claim_amount)),
            'claim_amount_date' => old('claim_amount_date', $case?->claim_amount_date?->format('Y-m-d')),
            'provisioned_amount' => old('provisioned_amount', $this->moneyForInput($case?->provisioned_amount)),
            'provisioned_amount_date' => old('provisioned_amount_date', $case?->provisioned_amount_date?->format('Y-m-d')),
            'court_paid_amount' => old('court_paid_amount', $this->moneyForInput($case?->court_paid_amount)),
            'court_paid_amount_date' => old('court_paid_amount_date', $case?->court_paid_amount_date?->format('Y-m-d')),
            'process_cost_amount' => old('process_cost_amount', $this->moneyForInput($case?->process_cost_amount)),
            'process_cost_amount_date' => old('process_cost_amount_date', $case?->process_cost_amount_date?->format('Y-m-d')),
            'sentence_amount' => old('sentence_amount', $this->moneyForInput($case?->sentence_amount)),
            'sentence_amount_date' => old('sentence_amount_date', $case?->sentence_amount_date?->format('Y-m-d')),
            'win_probability_option_id' => old('win_probability_option_id', $case?->win_probability_option_id),
            'notes' => old('notes', $case?->notes),
            'closed_at' => old('closed_at', $case?->closed_at?->format('Y-m-d')),
            'closed_by' => old('closed_by', $case?->closed_by),
            'closure_type_option_id' => old('closure_type_option_id', $case?->closure_type_option_id),
            'closure_notes' => old('closure_notes', $case?->closure_notes),
        ];
    }

    private function optionsForForms()
    {
        return ProcessCaseOption::query()
            ->active()
            ->get()
            ->groupBy('group_key');
    }

    private function entitiesForLookup()
    {
        return ClientEntity::query()
            ->where('is_active', 1)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'legal_name', 'cpf_cnpj']);
    }

    private function condominiumsForLookup()
    {
        return ClientCondominium::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'cnpj']);
    }

    private function resolveEntity(string $name): ?ClientEntity
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $name);

        return ClientEntity::query()
            ->where(function ($query) use ($name, $digits) {
                $query->where('display_name', $name)
                    ->orWhere('legal_name', $name);
                if ($digits !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') = ?", [$digits]);
                }
            })
            ->orderBy('display_name')
            ->first();
    }

    private function optionId(Request $request, string $field, string $group): ?int
    {
        $id = (int) $request->integer($field);
        if ($id <= 0) {
            return null;
        }

        return ProcessCaseOption::query()->whereKey($id)->where('group_key', $group)->value('id') ?: null;
    }

    private function money(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(['R$', ' ', '.'], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    private function moneyForInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    private function formatProcessNumber(string $value): ?string
    {
        $value = trim($value);
        $digits = preg_replace('/\D+/', '', $value);

        if (strlen($digits) === 20) {
            return preg_replace('/(\d{7})(\d{2})(\d{4})(\d{1})(\d{2})(\d{4})/', '$1-$2.$3.$4.$5.$6', $digits);
        }

        return $value !== '' ? $value : null;
    }

    private function applyVisibility($query, Request $request): void
    {
        $user = AncoraAuth::user($request);
        if ($user?->isSuperadmin()) {
            return;
        }

        $needleName = $this->normalize($user?->name);
        $needleEmail = $this->normalize($user?->email);

        $query->where(function ($inner) use ($user, $needleName, $needleEmail) {
            $inner->where('is_private', false)
                ->orWhere('created_by', $user?->id);

            if ($needleName !== '') {
                $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleName . '%']);
            }
            if ($needleEmail !== '') {
                $inner->orWhereRaw('LOWER(responsible_lawyer) like ?', ['%' . $needleEmail . '%']);
            }
        });
    }

    private function visibleProcessQuery(Request $request)
    {
        $query = ProcessCase::query();
        $this->applyVisibility($query, $request);

        return $query;
    }

    private function visibleProcessPhaseQuery(Request $request)
    {
        return ProcessCasePhase::query()
            ->whereHas('processCase', function ($query) use ($request) {
                $this->applyVisibility($query, $request);
            });
    }

    private function processReferenceDate(ProcessCase $case): ?Carbon
    {
        $date = $case->opened_at ?: $case->created_at;

        return $date ? Carbon::parse($date) : null;
    }

    private function phaseReferenceDate(ProcessCasePhase $phase): ?Carbon
    {
        $date = $phase->phase_date ?: $phase->created_at;

        return $date ? Carbon::parse($date) : null;
    }

    private function lastMovementDate(ProcessCase $case): ?Carbon
    {
        $date = $case->phases_max_phase_date ?: $this->processReferenceDate($case);

        return $date ? Carbon::parse($date) : null;
    }

    private function optionDistribution($cases, string $relation, string $fallback): array
    {
        return $cases
            ->groupBy(fn (ProcessCase $case) => $case->{$relation}?->name ?: $fallback)
            ->map(function ($group, string $label) use ($relation) {
                $first = $group->first();
                $option = $first ? $first->{$relation} : null;

                return [
                    'label' => $label,
                    'count' => $group->count(),
                    'color' => $option?->color_hex ?: '#6B7280',
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function authorizeProcessAccess(Request $request, ProcessCase $case): void
    {
        abort_unless($this->canAccess($request, $case), 403);
    }

    private function canAccess(Request $request, ProcessCase $case): bool
    {
        if (!$case->is_private) {
            return true;
        }

        $user = AncoraAuth::user($request);
        if (!$user) {
            return false;
        }

        if ($user->isSuperadmin() || (int) $case->created_by === (int) $user->id) {
            return true;
        }

        $responsible = $this->normalize($case->responsible_lawyer);
        $userName = $this->normalize($user->name);
        $userEmail = $this->normalize($user->email);

        return $responsible !== ''
            && (($userName !== '' && ($responsible === $userName || str_contains($responsible, $userName)))
                || ($userEmail !== '' && ($responsible === $userEmail || str_contains($responsible, $userEmail))));
    }

    private function normalize(?string $value): string
    {
        return Str::of(Str::ascii((string) $value))->lower()->squish()->toString();
    }

    private function storeAttachments(Request $request, ProcessCase $processo, ?ProcessCasePhase $phase, string $role): int
    {
        $files = $this->normalizeUploadedFiles($request->file('files'));
        if ($files === []) {
            return 0;
        }

        $dir = public_path('uploads/processos/' . $processo->id);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return 0;
        }

        $uploaded = 0;
        $user = AncoraAuth::user($request);
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'], true)) {
                continue;
            }

            $stored = now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
            $file->move($dir, $stored);
            $path = $dir . DIRECTORY_SEPARATOR . $stored;

            ProcessCaseAttachment::query()->create([
                'process_case_id' => $processo->id,
                'phase_id' => $phase?->id,
                'file_role' => Str::limit($role, 50, ''),
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 255, ''),
                'stored_name' => $stored,
                'relative_path' => '/uploads/processos/' . $processo->id . '/' . $stored,
                'mime_type' => Str::limit((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''), 120, ''),
                'file_size' => is_file($path) ? (int) (@filesize($path) ?: 0) : 0,
                'uploaded_by' => $user?->id,
            ]);
            $uploaded++;
        }

        return $uploaded;
    }

    private function normalizeUploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter($files));
        }

        return [];
    }

    private function deletePhysicalAttachment(ProcessCaseAttachment $attachment): void
    {
        $path = public_path(ltrim((string) $attachment->relative_path, '/'));
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
