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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function import(Request $request): View
    {
        $importPreviewToken = (string) $request->session()->get('process_import_preview_token', '');
        $importPreview = $importPreviewToken !== ''
            ? $request->session()->get("process_import_previews.{$importPreviewToken}")
            : null;

        return view('pages.processos.import', [
            'title' => 'Importacao de processos',
            'importPreviewToken' => $importPreviewToken,
            'importPreview' => $importPreview,
            'templateHeaders' => $this->processImportTemplateHeaders(),
            'phaseTemplateHeaders' => $this->processImportPhaseHeaders(),
            'options' => $this->optionsForForms(),
            'condominiums' => $this->condominiumsForLookup(),
        ]);
    }

    public function downloadImportTemplate(): StreamedResponse
    {
        $headers = array_merge($this->processImportTemplateHeaders(), $this->processImportPhaseHeaders());
        $rows = [[
            'responsible_lawyer' => 'Ana Paula Ramos',
            'opened_at' => '2026-04-15',
            'process_number' => '5001234-98.2026.8.08.0024',
            'datajud_court' => 'api_publica_tjes',
            'status' => 'Ativo',
            'action_type' => 'Cobranca',
            'process_type' => 'Judicial',
            'nature' => 'Condominial',
            'judging_body' => '2a Vara Civel de Vitoria',
            'client_name' => 'Condominio Residencial Horizonte',
            'client_condominium' => 'Condominio Residencial Horizonte',
            'adverse_name' => 'Carlos Henrique da Silva',
            'adverse_condominium' => '',
            'client_position' => 'Autor',
            'adverse_position' => 'Reu',
            'client_lawyer' => 'Equipe Ancora',
            'adverse_lawyer' => 'Paulo Matos',
            'is_private' => 'nao',
            'claim_amount' => '125000,00',
            'claim_amount_date' => '2026-04-15',
            'provisioned_amount' => '90000,00',
            'provisioned_amount_date' => '2026-04-16',
            'court_paid_amount' => '0,00',
            'court_paid_amount_date' => '',
            'process_cost_amount' => '3500,00',
            'process_cost_amount_date' => '2026-04-18',
            'sentence_amount' => '',
            'sentence_amount_date' => '',
            'win_probability' => 'Provavel',
            'notes' => 'Processo importado em lote para conferencia inicial.',
            'closed_at' => '',
            'closed_by' => '',
            'closure_type' => '',
            'closure_notes' => '',
            'phase_1_date' => '2026-04-15',
            'phase_1_time' => '14:30',
            'phase_1_description' => 'Distribuicao',
            'phase_1_private' => 'nao',
            'phase_1_reviewed' => 'sim',
            'phase_1_notes' => 'Distribuicao inicial do processo.',
            'phase_1_legal_opinion' => 'Peticao inicial protocolada.',
            'phase_1_conference' => 'Conferido com o protocolo.',
            'phase_2_date' => '2026-04-29',
            'phase_2_time' => '10:00',
            'phase_2_description' => 'Despacho inicial',
            'phase_2_private' => 'nao',
            'phase_2_reviewed' => 'nao',
            'phase_2_notes' => 'Despacho para citacao da parte adversa.',
            'phase_2_legal_opinion' => '',
            'phase_2_conference' => '',
            'phase_3_date' => '',
            'phase_3_time' => '',
            'phase_3_description' => '',
            'phase_3_private' => '',
            'phase_3_reviewed' => '',
            'phase_3_notes' => '',
            'phase_3_legal_opinion' => '',
            'phase_3_conference' => '',
        ]];

        return response()->streamDownload(function () use ($headers, $rows) {
            $stream = fopen('php://output', 'w');
            if (!$stream) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ';');

            foreach ($rows as $row) {
                $ordered = [];
                foreach ($headers as $header) {
                    $ordered[] = $row[$header] ?? '';
                }

                fputcsv($stream, $ordered, ';');
            }

            fclose($stream);
        }, 'processos-importacao-exemplo.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importPreview(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        try {
            $rows = $this->readProcessImportCsv($request->file('import_file'));
            $preview = $this->prepareProcessImportPreview($rows);
            $token = (string) Str::uuid();

            $request->session()->put("process_import_previews.{$token}", $preview);

            $message = ($preview['summary']['errors'] ?? 0) > 0
                ? "Previa gerada com {$preview['summary']['errors']} pendencia(s). Corrija as linhas sinalizadas antes de executar."
                : "Previa gerada com {$preview['summary']['ready']} processo(s) pronto(s) para importacao.";

            return redirect()->route('processos.import.index')
                ->with('process_import_preview_token', $token)
                ->with(($preview['summary']['errors'] ?? 0) > 0 ? 'error' : 'success', $message);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Nao foi possivel gerar a previa da importacao agora. Revise o CSV e tente novamente.');
        }
    }

    public function importExecute(Request $request): RedirectResponse
    {
        $token = (string) $request->input('import_token', '');
        $preview = $token !== '' ? $request->session()->get("process_import_previews.{$token}") : null;

        if (!$preview || empty($preview['rows'])) {
            return redirect()->route('processos.import.index')->with('error', 'A previa da importacao expirou. Envie o CSV novamente.');
        }

        $rows = $this->applyProcessImportResolutions((array) $preview['rows'], $request);
        $preview = $this->prepareProcessImportPreview($rows);
        $request->session()->put("process_import_previews.{$token}", $preview);

        if (($preview['summary']['errors'] ?? 0) > 0) {
            return redirect()->route('processos.import.index')
                ->with('process_import_preview_token', $token)
                ->with('error', 'A importacao nao foi executada porque ainda existem linhas invalidas ou duplicadas na previa.');
        }

        $user = AncoraAuth::user($request);

        try {
            $created = DB::transaction(function () use ($preview, $user) {
                $created = 0;

                foreach ((array) $preview['rows'] as $row) {
                    if (($row['preview_status'] ?? '') !== 'ready') {
                        continue;
                    }

                    $payload = $this->payloadFromImportRow($row);
                    $case = ProcessCase::query()->create($payload + [
                        'created_by' => $user?->id,
                        'updated_by' => $user?->id,
                    ]);

                    foreach ((array) ($row['phases'] ?? []) as $phase) {
                        ProcessCasePhase::query()->create([
                            'process_case_id' => $case->id,
                            'phase_date' => $phase['phase_date'] ?? null,
                            'phase_time' => $phase['phase_time'] ?? null,
                            'description' => $phase['description'],
                            'is_private' => (bool) ($phase['is_private'] ?? false),
                            'is_reviewed' => (bool) ($phase['is_reviewed'] ?? false),
                            'notes' => $phase['notes'] ?? null,
                            'legal_opinion' => $phase['legal_opinion'] ?? null,
                            'conference' => $phase['conference'] ?? null,
                            'source' => 'manual',
                            'created_by' => $user?->id,
                        ]);
                    }

                    $created++;
                }

                return $created;
            });

            $request->session()->forget("process_import_previews.{$token}");

            return redirect()->route('processos.index')->with('success', "Importacao executada. {$created} processo(s) criado(s).");
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('processos.import.index')
                ->with('process_import_preview_token', $token)
                ->with('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Nao foi possivel executar a importacao agora. Tente novamente.');
        }
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
            'adverseCondominium',
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
            'adverse_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'client_lawyer' => ['nullable', 'string', 'max:160'],
            'adverse_lawyer' => ['nullable', 'string', 'max:160'],
            'judging_body' => ['nullable', 'string', 'max:190'],
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
        $clientCondominium = $this->resolveCondominium((string) $request->input('client_name', ''));
        $adverseCondominium = $this->resolveCondominium((string) $request->input('adverse_name', ''));
        $clientCondominiumId = (int) ($validated['client_condominium_id'] ?? 0) ?: (int) ($clientCondominium?->id ?? 0);
        $adverseCondominiumId = (int) ($validated['adverse_condominium_id'] ?? 0) ?: (int) ($adverseCondominium?->id ?? 0);
        $selectedClientCondominium = $clientCondominiumId ? ClientCondominium::query()->find($clientCondominiumId) : null;
        $selectedAdverseCondominium = $adverseCondominiumId ? ClientCondominium::query()->find($adverseCondominiumId) : null;
        $clientNameInput = trim((string) ($validated['client_name'] ?? ''));
        $adverseNameInput = trim((string) ($validated['adverse_name'] ?? ''));

        $payload = [
            'responsible_lawyer' => $validated['responsible_lawyer'] ?? null,
            'opened_at' => $validated['opened_at'] ?? null,
            'process_number' => $this->formatProcessNumber((string) ($validated['process_number'] ?? '')),
            'datajud_court' => $validated['datajud_court'] ?? null,
            'status_option_id' => $this->optionId($request, 'status_option_id', 'status'),
            'action_type_option_id' => $this->optionId($request, 'action_type_option_id', 'action_type'),
            'process_type_option_id' => $this->optionId($request, 'process_type_option_id', 'process_type'),
            'client_entity_id' => $client?->id,
            'client_condominium_id' => $clientCondominiumId ?: null,
            'client_name_snapshot' => $client?->display_name ?: ($clientCondominium?->name ?: ($clientNameInput !== '' ? $clientNameInput : $selectedClientCondominium?->name)),
            'adverse_entity_id' => $adverse?->id,
            'adverse_name' => $adverse?->display_name ?: ($adverseCondominium?->name ?: ($adverseNameInput !== '' ? $adverseNameInput : $selectedAdverseCondominium?->name)),
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

        if ($this->processHasJudgingBodyColumn()) {
            $payload['judging_body'] = $this->normalizeWhitespace((string) ($validated['judging_body'] ?? '')) ?: null;
        }

        if ($this->processHasAdverseCondominiumColumn()) {
            $payload['adverse_condominium_id'] = $adverseCondominiumId ?: null;
        }

        return $payload;
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
            'adverse_condominium_id' => old('adverse_condominium_id', $case?->adverse_condominium_id),
            'client_position_option_id' => old('client_position_option_id', $case?->client_position_option_id),
            'adverse_position_option_id' => old('adverse_position_option_id', $case?->adverse_position_option_id),
            'client_lawyer' => old('client_lawyer', $case?->client_lawyer),
            'adverse_lawyer' => old('adverse_lawyer', $case?->adverse_lawyer),
            'nature_option_id' => old('nature_option_id', $case?->nature_option_id),
            'judging_body' => old('judging_body', $case?->judging_body),
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
        $entities = ClientEntity::query()
            ->where('is_active', 1)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'legal_name', 'cpf_cnpj'])
            ->map(fn (ClientEntity $entity) => (object) [
                'display_name' => $entity->display_name,
                'legal_name' => $entity->legal_name,
                'cpf_cnpj' => $entity->cpf_cnpj,
            ]);

        $condominiums = ClientCondominium::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'cnpj'])
            ->map(fn (ClientCondominium $condominium) => (object) [
                'display_name' => $condominium->name,
                'legal_name' => 'Condominio',
                'cpf_cnpj' => $condominium->cnpj,
            ]);

        return $entities
            ->merge($condominiums)
            ->sortBy('display_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
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

    private function resolveCondominium(string $name): ?ClientCondominium
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $name);

        return ClientCondominium::query()
            ->where(function ($query) use ($name, $digits) {
                $query->where('name', $name);
                if ($digits !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '-', ''), '/', '') = ?", [$digits]);
                }
            })
            ->orderBy('name')
            ->first();
    }

    private function processHasAdverseCondominiumColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            return $hasColumn = Schema::hasColumn('process_cases', 'adverse_condominium_id');
        } catch (\Throwable) {
            return $hasColumn = false;
        }
    }

    private function processHasJudgingBodyColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            return $hasColumn = Schema::hasColumn('process_cases', 'judging_body');
        } catch (\Throwable) {
            return $hasColumn = false;
        }
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

    private function readProcessImportCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \RuntimeException('Nao foi possivel abrir a planilha CSV para importacao.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new \RuntimeException('A planilha CSV esta vazia.');
            }

            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);

            $header = fgetcsv($handle, 0, $delimiter);
            if (!$header) {
                throw new \RuntimeException('A planilha CSV esta vazia.');
            }

            $header[0] = preg_replace("/^\xEF\xBB\xBF/", '', (string) ($header[0] ?? '')) ?? (string) ($header[0] ?? '');
            $header = array_map(fn ($value) => $this->normalizeCsvHeader((string) $value), $header);

            $rows = [];
            $rowNumber = 1;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $data = [];
                foreach ($header as $index => $column) {
                    $data[$column] = $row[$index] ?? '';
                }

                $rows[] = $this->normalizeProcessImportRow($data, $rowNumber);
            }

            if ($rows === []) {
                throw new \RuntimeException('Nenhuma linha valida foi encontrada no CSV.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function normalizeProcessImportRow(array $data, int $rowNumber): array
    {
        return [
            'row_number' => $rowNumber,
            'responsible_lawyer' => $this->normalizeWhitespace($this->csvField($data, ['responsible_lawyer', 'advogado_responsavel', 'responsavel'])),
            'opened_at' => $this->normalizeDateValue($this->csvField($data, ['opened_at', 'data_abertura'])),
            'process_number' => $this->formatProcessNumber($this->csvField($data, ['process_number', 'numero_processo', 'processo', 'numero_cnj'])),
            'datajud_court' => $this->normalizeWhitespace($this->csvField($data, ['datajud_court', 'tribunal_datajud', 'tribunal'])),
            'status' => $this->normalizeWhitespace($this->csvField($data, ['status'])),
            'action_type' => $this->normalizeWhitespace($this->csvField($data, ['action_type', 'tipo_acao'])),
            'process_type' => $this->normalizeWhitespace($this->csvField($data, ['process_type', 'tipo_processo'])),
            'nature' => $this->normalizeWhitespace($this->csvField($data, ['nature', 'natureza'])),
            'judging_body' => $this->normalizeWhitespace($this->csvField($data, ['judging_body', 'vara_setor', 'orgao_setor', 'orgao_julgador', 'vara', 'setor'])),
            'client_name' => $this->normalizeWhitespace($this->csvField($data, ['client_name', 'cliente'])),
            'client_condominium' => $this->normalizeWhitespace($this->csvField($data, ['client_condominium', 'cliente_condominio', 'client_condominium_id', 'condominio_cliente'])),
            'adverse_name' => $this->normalizeWhitespace($this->csvField($data, ['adverse_name', 'adverso'])),
            'adverse_condominium' => $this->normalizeWhitespace($this->csvField($data, ['adverse_condominium', 'adverso_condominio', 'adverse_condominium_id', 'condominio_adverso'])),
            'client_position' => $this->normalizeWhitespace($this->csvField($data, ['client_position', 'posicao_cliente'])),
            'adverse_position' => $this->normalizeWhitespace($this->csvField($data, ['adverse_position', 'posicao_adverso'])),
            'client_lawyer' => $this->normalizeWhitespace($this->csvField($data, ['client_lawyer', 'advogado_cliente'])),
            'adverse_lawyer' => $this->normalizeWhitespace($this->csvField($data, ['adverse_lawyer', 'advogado_adverso'])),
            'is_private' => $this->normalizeBooleanValue($this->csvField($data, ['is_private', 'particular'])),
            'claim_amount' => $this->money($this->csvField($data, ['claim_amount', 'valor_causa'])),
            'claim_amount_date' => $this->normalizeDateValue($this->csvField($data, ['claim_amount_date', 'data_valor_causa'])),
            'provisioned_amount' => $this->money($this->csvField($data, ['provisioned_amount', 'valor_provisionado'])),
            'provisioned_amount_date' => $this->normalizeDateValue($this->csvField($data, ['provisioned_amount_date', 'data_valor_provisionado'])),
            'court_paid_amount' => $this->money($this->csvField($data, ['court_paid_amount', 'total_pago_juizo'])),
            'court_paid_amount_date' => $this->normalizeDateValue($this->csvField($data, ['court_paid_amount_date', 'data_total_pago_juizo'])),
            'process_cost_amount' => $this->money($this->csvField($data, ['process_cost_amount', 'custo_processo'])),
            'process_cost_amount_date' => $this->normalizeDateValue($this->csvField($data, ['process_cost_amount_date', 'data_custo_processo'])),
            'sentence_amount' => $this->money($this->csvField($data, ['sentence_amount', 'valor_sentenca'])),
            'sentence_amount_date' => $this->normalizeDateValue($this->csvField($data, ['sentence_amount_date', 'data_valor_sentenca'])),
            'win_probability' => $this->normalizeWhitespace($this->csvField($data, ['win_probability', 'possibilidade_ganho', 'probabilidade_ganho'])),
            'notes' => $this->normalizeMultiline($this->csvField($data, ['notes', 'observacoes', 'observacoes_gerais'])),
            'closed_at' => $this->normalizeDateValue($this->csvField($data, ['closed_at', 'data_encerramento'])),
            'closed_by' => $this->normalizeWhitespace($this->csvField($data, ['closed_by', 'encerrado_por', 'responsavel_encerramento'])),
            'closure_type' => $this->normalizeWhitespace($this->csvField($data, ['closure_type', 'tipo_encerramento'])),
            'closure_notes' => $this->normalizeMultiline($this->csvField($data, ['closure_notes', 'observacoes_encerramento'])),
            'phases' => $this->extractProcessImportPhases($data),
        ];
    }

    private function prepareProcessImportPreview(array $rows): array
    {
        $seen = [];
        $preparedRows = [];
        $summary = [
            'total' => 0,
            'ready' => 0,
            'errors' => 0,
            'phases' => 0,
        ];

        foreach ($rows as $row) {
            $messages = [];
            $processNumber = $this->formatProcessNumber((string) ($row['process_number'] ?? ''));
            $clientName = $this->normalizeWhitespace((string) ($row['client_name'] ?? ''));
            $adverseName = $this->normalizeWhitespace((string) ($row['adverse_name'] ?? ''));
            $processTypeId = $this->resolveImportOptionId('process_type', (string) ($row['process_type'] ?? ''));
            $processTypeLabel = $this->resolveImportOptionName('process_type', (string) ($row['process_type'] ?? ''));
            $statusValue = trim((string) ($row['status_value'] ?? $row['status'] ?? ''));
            $statusLabel = $this->resolveImportOptionName('status', $statusValue);
            $court = $this->resolveImportCourt((string) ($row['datajud_court'] ?? ''));
            $clientCondominium = $this->resolveImportCondominium((string) ($row['client_condominium'] ?? ''));
            $adverseCondominium = $this->resolveImportCondominium((string) ($row['adverse_condominium'] ?? ''));
            $phases = $this->normalizeImportedPhases((array) ($row['phases'] ?? []), $messages);

            if ($processNumber === '' && $clientName === '' && $adverseName === '') {
                $messages[] = 'Informe ao menos processo, cliente ou adverso.';
            }

            if ($statusValue !== '' && !$this->resolveImportOptionId('status', $statusValue)) {
                $messages[] = 'Status nao encontrado nas configuracoes.';
            }

            if ((string) ($row['action_type'] ?? '') !== '' && !$this->resolveImportOptionId('action_type', (string) $row['action_type'])) {
                $messages[] = 'Tipo de acao nao encontrado nas configuracoes.';
            }

            if ((string) ($row['process_type'] ?? '') !== '' && !$processTypeId) {
                $messages[] = 'Tipo de processo nao encontrado nas configuracoes.';
            }

            if ((string) ($row['nature'] ?? '') !== '' && !$this->resolveImportOptionId('nature', (string) $row['nature'])) {
                $messages[] = 'Natureza nao encontrada nas configuracoes.';
            }

            if ((string) ($row['client_position'] ?? '') !== '' && !$this->resolveImportOptionId('client_position', (string) $row['client_position'])) {
                $messages[] = 'Posicao do cliente nao encontrada nas configuracoes.';
            }

            if ((string) ($row['adverse_position'] ?? '') !== '' && !$this->resolveImportOptionId('adverse_position', (string) $row['adverse_position'])) {
                $messages[] = 'Posicao do adverso nao encontrada nas configuracoes.';
            }

            if ((string) ($row['win_probability'] ?? '') !== '' && !$this->resolveImportOptionId('win_probability', (string) $row['win_probability'])) {
                $messages[] = 'Possibilidade de ganho nao encontrada nas configuracoes.';
            }

            if ((string) ($row['closure_type'] ?? '') !== '' && !$this->resolveImportOptionId('closure_type', (string) $row['closure_type'])) {
                $messages[] = 'Tipo de encerramento nao encontrado nas configuracoes.';
            }

            if ((string) ($row['datajud_court'] ?? '') !== '' && !$court) {
                $messages[] = 'Tribunal DataJud nao encontrado nas configuracoes.';
            }

            if ((string) ($row['client_condominium'] ?? '') !== '' && !$clientCondominium) {
                $messages[] = 'Condominio do cliente nao encontrado.';
            }

            if ((string) ($row['adverse_condominium'] ?? '') !== '' && !$adverseCondominium) {
                $messages[] = 'Condominio do adverso nao encontrado.';
            }

            if ($processNumber !== '') {
                $duplicateKey = $this->processImportDuplicateKey($processNumber);
                if (isset($seen[$duplicateKey])) {
                    $messages[] = 'Duplicado dentro da propria planilha com a linha ' . $seen[$duplicateKey] . '.';
                } else {
                    $seen[$duplicateKey] = (int) ($row['row_number'] ?? 0);
                }

                if ($this->processNumberAlreadyExists($processNumber)) {
                    $messages[] = 'Ja existe processo cadastrado com este numero.';
                }
            }

            $row['process_number'] = $processNumber;
            $row['status_value'] = $statusValue;
            $row['process_type_label'] = $processTypeLabel ?: (($row['process_type'] ?? '') ?: 'Nao informado');
            $row['status_label'] = $statusLabel ?: ($statusValue !== '' ? $statusValue : 'Sem status');
            $row['datajud_court_slug'] = $court?->slug;
            $row['client_condominium_id'] = $clientCondominium?->id;
            $row['client_condominium_name'] = $clientCondominium?->name;
            $row['adverse_condominium_id'] = $adverseCondominium?->id;
            $row['adverse_condominium_name'] = $adverseCondominium?->name;
            $row['phases'] = $phases;
            $row['phase_count'] = count($phases);
            $row['preview_status'] = $messages === [] ? 'ready' : 'error';
            $row['messages'] = $messages;

            $preparedRows[] = $row;
            $summary['total']++;
            $summary[$row['preview_status'] === 'ready' ? 'ready' : 'errors']++;
            $summary['phases'] += $row['phase_count'];
        }

        return [
            'rows' => $preparedRows,
            'summary' => $summary,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    private function applyProcessImportResolutions(array $rows, Request $request): array
    {
        $resolutions = (array) $request->input('resolutions', []);

        if ($resolutions === []) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $rowKey = (string) ($row['row_number'] ?? '');
            if ($rowKey === '' || !isset($resolutions[$rowKey]) || !is_array($resolutions[$rowKey])) {
                continue;
            }

            $resolution = $resolutions[$rowKey];

            $actionTypeId = (int) ($resolution['action_type_option_id'] ?? 0);
            if ($actionTypeId > 0 && $this->importOptionExistsForGroup('action_type', $actionTypeId)) {
                $row['action_type'] = (string) $actionTypeId;
            }

            $natureId = (int) ($resolution['nature_option_id'] ?? 0);
            if ($natureId > 0 && $this->importOptionExistsForGroup('nature', $natureId)) {
                $row['nature'] = (string) $natureId;
            }

            $this->applyCondominiumImportResolution($row, $resolution, 'client');
            $this->applyCondominiumImportResolution($row, $resolution, 'adverse');
        }
        unset($row);

        return $rows;
    }

    private function applyCondominiumImportResolution(array &$row, array $resolution, string $party): void
    {
        $modeKey = "{$party}_condominium_mode";
        $idKey = "{$party}_condominium_id";
        $rowField = "{$party}_condominium";
        $ignoreField = "{$party}_condominium_ignore";

        $mode = (string) ($resolution[$modeKey] ?? '');

        if ($mode === 'ignore') {
            $row[$rowField] = '';
            $row[$ignoreField] = true;

            return;
        }

        if ($mode === 'select') {
            $condominiumId = (int) ($resolution[$idKey] ?? 0);
            if ($condominiumId > 0 && ClientCondominium::query()->whereKey($condominiumId)->exists()) {
                $row[$rowField] = (string) $condominiumId;
                $row[$ignoreField] = false;
            }
        }
    }

    private function importOptionExistsForGroup(string $group, int $id): bool
    {
        return $this->importOptionsForGroup($group)
            ->contains(fn (ProcessCaseOption $option) => (int) $option->id === $id);
    }

    private function payloadFromImportRow(array $row): array
    {
        $clientName = trim((string) ($row['client_name'] ?? ''));
        $adverseName = trim((string) ($row['adverse_name'] ?? ''));
        $client = $this->resolveEntity($clientName);
        $adverse = $this->resolveEntity($adverseName);
        $clientCondominium = !empty($row['client_condominium_ignore'])
            ? null
            : ($this->resolveImportCondominium((string) ($row['client_condominium'] ?? '')) ?: $this->resolveCondominium($clientName));
        $adverseCondominium = !empty($row['adverse_condominium_ignore'])
            ? null
            : ($this->resolveImportCondominium((string) ($row['adverse_condominium'] ?? '')) ?: $this->resolveCondominium($adverseName));

        $payload = [
            'responsible_lawyer' => $this->emptyToNull($row['responsible_lawyer'] ?? null),
            'opened_at' => $this->emptyToNull($row['opened_at'] ?? null),
            'process_number' => $this->formatProcessNumber((string) ($row['process_number'] ?? '')),
            'datajud_court' => $this->resolveImportCourt((string) ($row['datajud_court'] ?? ''))?->slug,
            'status_option_id' => $this->resolveImportOptionId('status', (string) ($row['status_value'] ?? $row['status'] ?? '')),
            'action_type_option_id' => $this->resolveImportOptionId('action_type', (string) ($row['action_type'] ?? '')),
            'process_type_option_id' => $this->resolveImportOptionId('process_type', (string) ($row['process_type'] ?? '')),
            'client_entity_id' => $client?->id,
            'client_condominium_id' => $clientCondominium?->id,
            'client_name_snapshot' => $client?->display_name ?: ($clientCondominium?->name ?: $this->emptyToNull($clientName)),
            'adverse_entity_id' => $adverse?->id,
            'adverse_name' => $adverse?->display_name ?: ($adverseCondominium?->name ?: $this->emptyToNull($adverseName)),
            'client_position_option_id' => $this->resolveImportOptionId('client_position', (string) ($row['client_position'] ?? '')),
            'adverse_position_option_id' => $this->resolveImportOptionId('adverse_position', (string) ($row['adverse_position'] ?? '')),
            'client_lawyer' => $this->emptyToNull($row['client_lawyer'] ?? null),
            'adverse_lawyer' => $this->emptyToNull($row['adverse_lawyer'] ?? null),
            'nature_option_id' => $this->resolveImportOptionId('nature', (string) ($row['nature'] ?? '')),
            'is_private' => (bool) ($row['is_private'] ?? false),
            'claim_amount' => $row['claim_amount'] ?? null,
            'claim_amount_date' => $this->emptyToNull($row['claim_amount_date'] ?? null),
            'provisioned_amount' => $row['provisioned_amount'] ?? null,
            'provisioned_amount_date' => $this->emptyToNull($row['provisioned_amount_date'] ?? null),
            'court_paid_amount' => $row['court_paid_amount'] ?? null,
            'court_paid_amount_date' => $this->emptyToNull($row['court_paid_amount_date'] ?? null),
            'process_cost_amount' => $row['process_cost_amount'] ?? null,
            'process_cost_amount_date' => $this->emptyToNull($row['process_cost_amount_date'] ?? null),
            'sentence_amount' => $row['sentence_amount'] ?? null,
            'sentence_amount_date' => $this->emptyToNull($row['sentence_amount_date'] ?? null),
            'win_probability_option_id' => $this->resolveImportOptionId('win_probability', (string) ($row['win_probability'] ?? '')),
            'notes' => $this->emptyToNull($row['notes'] ?? null),
            'closed_at' => $this->emptyToNull($row['closed_at'] ?? null),
            'closed_by' => $this->emptyToNull($row['closed_by'] ?? null),
            'closure_type_option_id' => $this->resolveImportOptionId('closure_type', (string) ($row['closure_type'] ?? '')),
            'closure_notes' => $this->emptyToNull($row['closure_notes'] ?? null),
        ];

        if ($this->processHasJudgingBodyColumn()) {
            $payload['judging_body'] = $this->emptyToNull($row['judging_body'] ?? null);
        }

        if ($this->processHasAdverseCondominiumColumn()) {
            $payload['adverse_condominium_id'] = $adverseCondominium?->id;
        }

        return $payload;
    }

    private function processImportTemplateHeaders(): array
    {
        return [
            'responsible_lawyer',
            'opened_at',
            'process_number',
            'datajud_court',
            'status',
            'action_type',
            'process_type',
            'nature',
            'judging_body',
            'client_name',
            'client_condominium',
            'adverse_name',
            'adverse_condominium',
            'client_position',
            'adverse_position',
            'client_lawyer',
            'adverse_lawyer',
            'is_private',
            'claim_amount',
            'claim_amount_date',
            'provisioned_amount',
            'provisioned_amount_date',
            'court_paid_amount',
            'court_paid_amount_date',
            'process_cost_amount',
            'process_cost_amount_date',
            'sentence_amount',
            'sentence_amount_date',
            'win_probability',
            'notes',
            'closed_at',
            'closed_by',
            'closure_type',
            'closure_notes',
        ];
    }

    private function processImportPhaseHeaders(): array
    {
        $headers = [];

        for ($index = 1; $index <= 3; $index++) {
            foreach (['date', 'time', 'description', 'private', 'reviewed', 'notes', 'legal_opinion', 'conference'] as $field) {
                $headers[] = "phase_{$index}_{$field}";
            }
        }

        return $headers;
    }

    private function extractProcessImportPhases(array $data): array
    {
        $groups = [];

        foreach ($data as $key => $value) {
            if (!preg_match('/^(phase|fase)_(\d+)_(.+)$/', (string) $key, $matches)) {
                continue;
            }

            $index = (int) $matches[2];
            $field = $this->normalizePhaseImportField($matches[3]);
            if ($field === null) {
                continue;
            }

            $groups[$index][$field] = is_string($value) ? trim($value) : $value;
        }

        ksort($groups);

        return array_values($groups);
    }

    private function normalizeImportedPhases(array $phases, array &$messages): array
    {
        $normalized = [];

        foreach ($phases as $index => $phase) {
            $date = $this->normalizeDateValue((string) ($phase['phase_date'] ?? $phase['date'] ?? ''));
            $time = $this->normalizeTimeValue((string) ($phase['phase_time'] ?? $phase['time'] ?? ''));
            $description = $this->normalizeWhitespace((string) ($phase['description'] ?? ''));
            $notes = $this->normalizeMultiline((string) ($phase['notes'] ?? ''));
            $legalOpinion = $this->normalizeMultiline((string) ($phase['legal_opinion'] ?? ''));
            $conference = $this->normalizeMultiline((string) ($phase['conference'] ?? ''));
            $hasAnyValue = $date || $time || $description !== '' || $notes !== '' || $legalOpinion !== '' || $conference !== ''
                || trim((string) ($phase['private'] ?? $phase['is_private'] ?? '')) !== ''
                || trim((string) ($phase['reviewed'] ?? $phase['is_reviewed'] ?? '')) !== '';

            if (!$hasAnyValue) {
                continue;
            }

            $phaseNumber = $index + 1;

            if (!$date) {
                $messages[] = "Fase {$phaseNumber}: informe a data.";
                continue;
            }

            $normalized[] = [
                'phase_date' => $date,
                'phase_time' => $time,
                'description' => $description !== '' ? Str::limit($description, 255, '') : 'Fase importada',
                'is_private' => $this->normalizeBooleanValue($phase['private'] ?? $phase['is_private'] ?? null),
                'is_reviewed' => $this->normalizeBooleanValue($phase['reviewed'] ?? $phase['is_reviewed'] ?? null),
                'notes' => $notes !== '' ? $notes : null,
                'legal_opinion' => $legalOpinion !== '' ? $legalOpinion : null,
                'conference' => $conference !== '' ? $conference : null,
            ];
        }

        return $normalized;
    }

    private function normalizePhaseImportField(string $field): ?string
    {
        $normalized = $this->normalizeCsvHeader($field);

        return match ($normalized) {
            'date', 'data' => 'date',
            'time', 'hora' => 'time',
            'description', 'descricao', 'desc' => 'description',
            'private', 'privada', 'is_private' => 'private',
            'reviewed', 'revisada', 'is_reviewed' => 'reviewed',
            'notes', 'observacoes', 'observacao' => 'notes',
            'legal_opinion', 'parecer', 'parecer_juridico' => 'legal_opinion',
            'conference', 'conferencia' => 'conference',
            default => null,
        };
    }

    private function resolveImportOptionId(string $group, string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $options = $this->importOptionsForGroup($group);
        if (ctype_digit($value)) {
            $numericId = (int) $value;
            $match = $options->first(fn (ProcessCaseOption $option) => (int) $option->id === $numericId);
            if ($match) {
                return (int) $match->id;
            }
        }

        $needle = $this->normalize($value);
        $match = $options->first(function (ProcessCaseOption $option) use ($needle) {
            return $this->normalize((string) $option->name) === $needle
                || $this->normalize((string) $option->slug) === $needle;
        });

        return $match ? (int) $match->id : null;
    }

    private function resolveImportOptionName(string $group, string $value): ?string
    {
        $id = $this->resolveImportOptionId($group, $value);
        if (!$id) {
            return null;
        }

        return $this->importOptionsForGroup($group)
            ->first(fn (ProcessCaseOption $option) => (int) $option->id === $id)
            ?->name;
    }

    private function importOptionsForGroup(string $group)
    {
        static $cache = [];

        if (!array_key_exists($group, $cache)) {
            $cache[$group] = ProcessCaseOption::query()
                ->where('group_key', $group)
                ->get(['id', 'group_key', 'name', 'slug']);
        }

        return $cache[$group];
    }

    private function resolveImportCourt(string $value): ?ProcessCaseOption
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $needle = $this->normalize($value);

        return $this->importOptionsForGroup('datajud_court')
            ->first(function (ProcessCaseOption $option) use ($needle) {
                return $this->normalize((string) $option->name) === $needle
                    || $this->normalize((string) $option->slug) === $needle;
            });
    }

    private function resolveImportCondominium(string $value): ?ClientCondominium
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $condominium = ClientCondominium::query()->find((int) $value);
            if ($condominium) {
                return $condominium;
            }
        }

        $digits = preg_replace('/\D+/', '', $value);

        return ClientCondominium::query()
            ->where(function ($query) use ($value, $digits) {
                $query->where('name', $value);
                if ($digits !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '-', ''), '/', '') = ?", [$digits]);
                }
            })
            ->orderBy('name')
            ->first();
    }

    private function processImportDuplicateKey(string $processNumber): string
    {
        $digits = preg_replace('/\D+/', '', $processNumber);

        return $digits !== '' ? $digits : $this->normalize($processNumber);
    }

    private function processNumberAlreadyExists(string $processNumber): bool
    {
        $formatted = $this->formatProcessNumber($processNumber);
        if (!$formatted) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $formatted);
        $query = ProcessCase::query();

        if ($digits !== '') {
            $query->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(process_number, '.', ''), '-', ''), '/', ''), ' ', ''), '_', '') = ?", [$digits]);
        } else {
            $query->where('process_number', $formatted);
        }

        return $query->exists();
    }

    private function normalizeCsvHeader(string $value): string
    {
        $value = Str::of(Str::ascii(trim($value)))->lower()->toString();
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim((string) $value, '_');
    }

    private function csvField(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function normalizeWhitespace(?string $value): string
    {
        return Str::of((string) $value)->squish()->toString();
    }

    private function normalizeMultiline(?string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $lines = collect(explode("\n", $value))
            ->map(fn (string $line) => $this->normalizeWhitespace($line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();

        return implode("\n", $lines);
    }

    private function normalizeDateValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && strlen($value) <= 5) {
            $serial = (int) $value;
            if ($serial > 0) {
                try {
                    return Carbon::create(1899, 12, 30)->addDays($serial)->format('Y-m-d');
                } catch (\Throwable) {
                }
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTimeValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('H:i:s');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeBooleanValue(mixed $value): bool
    {
        $value = $this->normalize((string) $value);

        return in_array($value, ['1', 'sim', 's', 'yes', 'y', 'true'], true);
    }

    private function emptyToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
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
