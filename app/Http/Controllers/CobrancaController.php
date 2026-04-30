<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientBlock;
use App\Models\ClientAttachment;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseAttachment;
use App\Models\CobrancaCaseContact;
use App\Models\CobrancaCaseEmailHistory;
use App\Models\CobrancaCaseInstallment;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaCaseTimeline;
use App\Models\CobrancaAgreementTerm;
use App\Models\CobrancaImportBatch;
use App\Models\CobrancaImportRow;
use App\Models\CobrancaMonetaryUpdate;
use App\Models\CobrancaMonetaryUpdateItem;
use App\Services\CobrancaAgreementTermService;
use App\Services\CobrancaMonetaryUpdateService;
use App\Support\AncoraAuth;
use App\Support\AncoraBillingMail;
use App\Support\AncoraSettings;
use App\Support\BrazilianCurrencyFormatter;
use App\Support\SortableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CobrancaController extends Controller
{
    public function dashboard(Request $request): View
    {
        $year = max(2024, (int) $request->integer('year', now()->year));
        $base = CobrancaCase::query()->where('charge_year', $year);
        $monthStart = now()->copy()->setDate($year, now()->month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthBase = CobrancaCase::query()->whereBetween('created_at', [$monthStart, $monthEnd]);

        $monthlyRows = CobrancaCase::query()
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month_number, COUNT(*) as cases_count, COALESCE(SUM(agreement_total), 0) as agreement_total, COALESCE(SUM(fees_amount), 0) as fees_total')
            ->groupByRaw('MONTH(created_at)')
            ->get()
            ->keyBy('month_number');

        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $agreementEvolution = [];
        $feesEvolution = [];
        $caseEvolution = [];

        foreach (range(1, 12) as $month) {
            $row = $monthlyRows->get($month);
            $agreementEvolution[] = round((float) ($row->agreement_total ?? 0), 2);
            $feesEvolution[] = round((float) ($row->fees_total ?? 0), 2);
            $caseEvolution[] = (int) ($row->cases_count ?? 0);
        }

        $summary = [
            'total' => (clone $base)->count(),
            'month_total' => (clone $monthBase)->count(),
            'notificar' => (clone $base)->whereIn('workflow_stage', ['apto_notificar', 'notificado'])->count(),
            'negociacao' => (clone $base)->whereIn('workflow_stage', ['em_negociacao', 'sem_retorno', 'aguardando_termo'])->count(),
            'aguardando_assinatura' => (clone $base)->where('workflow_stage', 'aguardando_assinatura')->count(),
            'acordo_ativo' => (clone $base)->whereIn('workflow_stage', ['acordo_ativo', 'aguardando_boletos'])->count(),
            'judicializar' => (clone $base)->where('workflow_stage', 'apto_judicializar')->count(),
            'ajuizado' => (clone $base)->where('situation', 'ajuizado')->count(),
            'encerrado' => (clone $base)->where('situation', 'pago_encerrado')->count(),
            'agreement_total' => (float) (clone $base)->sum('agreement_total'),
            'agreement_month_total' => (float) (clone $monthBase)->sum('agreement_total'),
            'entry_total' => (float) (clone $base)->sum('entry_amount'),
            'fees_total' => (float) (clone $base)->sum('fees_amount'),
            'fees_month_total' => (float) (clone $monthBase)->sum('fees_amount'),
            'month_label' => $monthLabels[now()->month - 1] . '/' . $year,
        ];

        $latest = CobrancaCase::query()
            ->with(['condominium', 'block', 'unit'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('pages.cobrancas.dashboard', [
            'title' => 'Dashboard de Cobrança',
            'year' => $year,
            'summary' => $summary,
            'latestCases' => $latest,
            'years' => CobrancaCase::query()->selectRaw('DISTINCT charge_year')->orderByDesc('charge_year')->pluck('charge_year'),
            'stageLabels' => $this->workflowStageLabels(),
            'chartData' => [
                'labels' => $monthLabels,
                'agreementTotals' => $agreementEvolution,
                'feesTotals' => $feesEvolution,
                'caseCounts' => $caseEvolution,
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $query = CobrancaCase::query()
            ->select('cobranca_cases.*')
            ->leftJoin('client_condominiums as cobranca_condominium_sort', 'cobranca_condominium_sort.id', '=', 'cobranca_cases.condominium_id')
            ->leftJoin('client_units as cobranca_unit_sort', 'cobranca_unit_sort.id', '=', 'cobranca_cases.unit_id')
            ->with(['condominium', 'block', 'unit'])
            ->withCount(['contacts', 'quotas', 'attachments']);

        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function ($sub) use ($term) {
                $sub->where('os_number', 'like', "%{$term}%")
                    ->orWhere('debtor_name_snapshot', 'like', "%{$term}%")
                    ->orWhere('debtor_document_snapshot', 'like', "%{$term}%")
                    ->orWhere('judicial_case_number', 'like', "%{$term}%")
                    ->orWhereHas('condominium', fn ($condo) => $condo->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('unit', fn ($unit) => $unit->where('unit_number', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('condominium_id')) {
            $query->where('cobranca_cases.condominium_id', (int) $request->integer('condominium_id'));
        }
        foreach (['charge_type', 'situation', 'workflow_stage', 'billing_status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where('cobranca_cases.' . $filter, (string) $request->input($filter));
            }
        }
        if ($request->filled('date_from')) $query->whereDate('cobranca_cases.created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('cobranca_cases.created_at', '<=', $request->input('date_to'));

        $sortState = SortableQuery::apply($query, $request, [
            'os' => 'cobranca_cases.os_number',
            'condominium' => 'cobranca_condominium_sort.name',
            'unit' => 'cobranca_unit_sort.unit_number',
            'debtor' => 'cobranca_cases.debtor_name_snapshot',
            'stage' => 'cobranca_cases.workflow_stage',
            'situation' => 'cobranca_cases.situation',
            'agreement_total' => 'cobranca_cases.agreement_total',
            'created_at' => 'cobranca_cases.created_at',
            'updated_at' => 'cobranca_cases.updated_at',
        ], 'created_at', 'desc');

        $items = $query->paginate(max(10, min(100, (int) $request->integer('per_page', 15))))->withQueryString();

        return view('pages.cobrancas.index', [
            'title' => 'Cobranças',
            'items' => $items,
            'filters' => $request->all(),
            'filterOptions' => $this->filterOptions(),
            'sortState' => $sortState,
        ]);
    }

    public function billingReport(Request $request): View
    {
        return view('pages.cobrancas.billing-report', array_merge([
            'title' => 'Relatório de faturamento',
            'pdfMode' => false,
        ], $this->billingReportData($request)));
    }

    public function billingReportPdf(Request $request): View|BinaryFileResponse
    {
        $viewData = array_merge([
            'title' => 'Relatório de faturamento',
            'autoPrint' => true,
            'pdfMode' => false,
        ], $this->billingReportData($request));

        $this->logAction($request, 'print_cobranca_billing_report', 0, 'PDF do relatório de faturamento de cobrança.');

        if ($pdfResponse = $this->billingReportPdfResponse($viewData)) {
            return $pdfResponse;
        }

        return view('pages.cobrancas.billing-report-pdf', $viewData);
    }


    public function importIndex(Request $request): View
    {
        return view('pages.cobrancas.import', [
            'title' => 'Importação de inadimplência',
            'batch' => null,
            'rows' => null,
            'summary' => $this->emptyImportSummary(),
            'statusOptions' => $this->importStatusLabels(),
            'statusStyles' => $this->importStatusStyles(),
            'filters' => [
                'status' => trim((string) $request->input('status', '')),
                'q' => trim((string) $request->input('q', '')),
            ],
            'recentBatches' => CobrancaImportBatch::query()->latest('id')->limit(8)->get(),
            'canProcessBatch' => false,
            'blockingRowsCount' => 0,
            'processedSummary' => [],
        ]);
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        $headers = [
            'Condominio',
            'CNPJ do Condominio (opcional)',
            'Bloco/Torre (opcional)',
            'Unidade',
            'Proprietario',
            'Referencia',
            'Vencimento',
            'Valor',
            'Tipo da Cota (opcional)',
        ];

        $rows = [[
            'Condominio Costa Allegra',
            '12.345.678/0001-99',
            'Bloco A',
            '401',
            'Joao Carlos Oliveira',
            '03/2026',
            '10/03/2026',
            '1250,80',
            'taxa_mes',
        ]];

        return $this->downloadSimpleXlsx('modelo_importacao_inadimplencia.xlsx', 'Modelo Importacao', $headers, $rows);
    }

    public function importPreview(Request $request): RedirectResponse
    {
        return $this->importPreviewV2($request);

        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $file = $request->file('spreadsheet');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return back()->with('error', 'Selecione uma planilha .xls ou .xlsx para importar.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            return back()->with('error', 'Formato inválido. Use apenas .xls ou .xlsx.');
        }

        $dir = storage_path('app/cobrancas-imports');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return back()->with('error', 'Não foi possível preparar a pasta de importações.');
        }

        $storedName = now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension;
        $file->move($dir, $storedName);
        $fullPath = $dir . DIRECTORY_SEPARATOR . $storedName;

        try {
            $parsed = $this->parseImportSpreadsheet($fullPath);
            $mappedRows = $this->mapSpreadsheetRowsToImport(
                $parsed['headers'] ?? [],
                $parsed['rows'] ?? [],
                (int) ($parsed['header_row_index'] ?? 1)
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Não foi possível analisar a planilha: ' . $e->getMessage());
        }

        if ($mappedRows === []) {
            return back()->with('error', 'A planilha não contém linhas válidas para importar.');
        }

        $batch = DB::transaction(function () use ($user, $file, $storedName, $parsed, $mappedRows) {
            /** @var CobrancaImportBatch $batch */
            $batch = CobrancaImportBatch::query()->create([
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 255, ''),
                'stored_name' => $storedName,
                'sheet_name' => Str::limit((string) ($parsed['sheet_name'] ?? 'Planilha 1'), 180, ''),
                'file_extension' => strtolower((string) $file->getClientOriginalExtension()),
                'status' => 'parsed',
                'uploaded_by' => $user->id,
            ]);

            foreach ($mappedRows as $row) {
                CobrancaImportRow::query()->create([
                    'batch_id' => $batch->id,
                    'row_number' => $row['row_number'],
                    'raw_payload_json' => $row['raw_payload_json'],
                    'condominium_input' => $row['condominium_input'],
                    'block_input' => $row['block_input'],
                    'unit_input' => $row['unit_input'],
                    'owner_input' => $row['owner_input'],
                    'reference_input' => $row['reference_input'],
                    'due_date_input' => $row['due_date_input'],
                    'amount_value' => $row['amount_value'],
                    'quota_type_input' => $row['quota_type_input'],
                    'status' => 'error_required',
                ]);
            }

            return $batch;
        });

        $this->classifyImportBatch($batch);
        $batch->refresh();
        $summary = $batch->summary_json ?? [];
        $this->logAction(
            $request,
            'cobrancas.import.preview',
            $batch->id,
            'Pré-análise da importação de inadimplência. Arquivo: ' . $batch->original_name . '. Linhas lidas: ' . ($summary['total_rows'] ?? $batch->total_rows) . '.'
        );

        $message = 'Planilha analisada. Revise a prévia, corrija inconsistências e confirme apenas o que estiver seguro.';
        if ((int) ($summary['blocking_rows'] ?? 0) > 0) {
            $message .= ' Existem ' . (int) $summary['blocking_rows'] . ' linha(s) com conflito ou erro impeditivo.';
        }

        return redirect()->route('cobrancas.import.show', $batch)->with('success', $message);
    }

    public function importShow(Request $request, CobrancaImportBatch $batch): View
    {
        return $this->importShowV2($request, $batch);

        $batch->load('user');
        $filters = [
            'status' => trim((string) $request->input('status', '')),
            'q' => trim((string) $request->input('q', '')),
        ];
        $rowsQuery = $batch->rows()
            ->with([
                'unit.condominium',
                'unit.block',
                'unit.owner',
                'cobrancaCase.creator',
            ])
            ->orderBy('row_number');

        if ($filters['status'] !== '' && $filters['status'] !== 'all') {
            $rowsQuery->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $rowsQuery->where(function ($query) use ($term) {
                $query->where('condominium_input', 'like', '%' . $term . '%')
                    ->orWhere('block_input', 'like', '%' . $term . '%')
                    ->orWhere('unit_input', 'like', '%' . $term . '%')
                    ->orWhere('owner_input', 'like', '%' . $term . '%')
                    ->orWhere('reference_input', 'like', '%' . $term . '%')
                    ->orWhere('message', 'like', '%' . $term . '%');
            });
        }

        $rows = $rowsQuery->paginate(80)->withQueryString();
        $summary = $this->importBatchSummary($batch);
        $blockingRowsCount = (int) ($summary['blocking_rows'] ?? 0);
        $readyRowsCount = (int) ($summary['ready_rows'] ?? 0);
        $canProcessBatch = $batch->status !== 'processed'
            && $batch->status !== 'cancelled'
            && $readyRowsCount > 0
            && $blockingRowsCount === 0;

        return view('pages.cobrancas.import', [
            'title' => 'Importação de inadimplência',
            'batch' => $batch,
            'rows' => $rows,
            'summary' => $summary,
            'statusOptions' => $this->importStatusLabels(),
            'statusStyles' => $this->importStatusStyles(),
            'filters' => $filters,
            'recentBatches' => CobrancaImportBatch::query()->where('id', '<>', $batch->id)->latest('id')->limit(8)->get(),
            'canProcessBatch' => $canProcessBatch,
            'blockingRowsCount' => $blockingRowsCount,
            'processedSummary' => $summary['processed'] ?? [],
        ]);
    }

    public function importProcess(Request $request, CobrancaImportBatch $batch): RedirectResponse
    {
        return $this->importProcessV2($request, $batch);

        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if ($batch->status === 'processed') {
            $hasProcessedCounters = ((int) $batch->created_cases + (int) $batch->updated_cases + (int) $batch->created_quotas + (int) $batch->duplicate_rows) > 0;
            $hasProcessedRows = $batch->rows()
                ->whereIn('status', ['created_case', 'updated_case', 'duplicate'])
                ->exists();

            if (!$hasProcessedCounters && !$hasProcessedRows) {
                $batch->update([
                    'status' => 'parsed',
                    'processed_at' => null,
                    'summary_json' => array_merge($batch->summary_json ?? [], [
                        'reopened_empty_processed_batch_at' => now()->toDateTimeString(),
                    ]),
                ]);
            } else {
                return redirect()->route('cobrancas.import.show', $batch)->with('success', 'Este lote já foi processado anteriormente.');
            }
        }

        $this->classifyImportBatch($batch);
        $batch->refresh();

        $readyRowsBeforeProcessing = (int) $batch->rows()->where('status', 'ready')->count();
        if ($readyRowsBeforeProcessing === 0) {
            $pendingRows = (int) $batch->rows()->where('status', 'pending')->count();

            return redirect()
                ->route('cobrancas.import.show', $batch)
                ->with('error', 'Nenhuma linha pronta para processar. O lote ficou com ' . $pendingRows . ' pendência(s); confira a coluna Detalhe e envie a planilha corrigida.');
        }

        $batch->load(['rows', 'rows.unit.owner']);

        $openCaseCache = [];
        $createdCases = 0;
        $updatedCases = 0;
        $createdQuotas = 0;
        $duplicateRows = 0;
        $errorRows = 0;

        foreach ($batch->rows as $row) {
            if ($row->status !== 'ready') {
                continue;
            }

            if (!$row->matched_unit_id || !$row->unit || !$row->unit->owner) {
                $row->update([
                    'status' => 'pending',
                    'message' => 'A linha não possui unidade/proprietário válido para gerar a OS.',
                ]);
                continue;
            }

            $reference = $this->normalizeReferenceLabel((string) $row->reference_input);
            $dueDate = $this->normalizeImportDate((string) $row->due_date_input);
            $amount = $row->amount_value !== null ? (float) $row->amount_value : null;

            if (!$this->isValidReferenceLabel($reference) || $dueDate === null || $amount === null) {
                $row->update([
                    'status' => 'pending',
                    'message' => 'Referência, vencimento e valor precisam estar válidos para processar a linha.',
                ]);
                continue;
            }

            $duplicateCase = $this->findActiveCaseWithQuota((int) $row->matched_unit_id, $reference, $dueDate);
            if ($duplicateCase) {
                $row->update([
                    'matched_case_id' => $duplicateCase->id,
                    'status' => 'duplicate',
                    'message' => 'Duplicidade detectada. A quota já existe na OS ' . $duplicateCase->os_number . '.',
                    'processed_at' => now(),
                ]);
                $duplicateRows++;
                continue;
            }

            $case = $openCaseCache[$row->matched_unit_id] ?? null;
            if (!$case) {
                $case = $this->findOpenCaseForUnit((int) $row->matched_unit_id);
                if ($case) {
                    $openCaseCache[$row->matched_unit_id] = $case;
                }
            }

            try {
                $wasNewCase = false;
                if (!$case) {
                    $case = DB::transaction(function () use ($row, $batch, $request, $user) {
                        return $this->createCaseFromImportRow($row, $batch, $request, $user->id);
                    });
                    $openCaseCache[$row->matched_unit_id] = $case->fresh();
                    $wasNewCase = true;
                    $createdCases++;
                }

                DB::transaction(function () use ($row, $case, $reference, $dueDate, $amount, $request, $wasNewCase) {
                    CobrancaCaseQuota::query()->create([
                        'cobranca_case_id' => $case->id,
                        'reference_label' => $reference,
                        'due_date' => $dueDate,
                        'original_amount' => $amount,
                        'updated_amount' => $amount,
                        'status' => 'taxa_mes',
                        'notes' => 'Importado automaticamente do lote #' . $row->batch_id . ' (linha ' . $row->row_number . ').',
                        'created_at' => now(),
                    ]);

                    $description = $wasNewCase
                        ? 'Referência ' . $reference . ' importada automaticamente via planilha de inadimplência.'
                        : 'Adicionada referência ' . $reference . ' automaticamente via planilha de inadimplência.';

                    $this->recordTimeline($case, 'import', $description, $request, now());

                    $row->update([
                        'matched_case_id' => $case->id,
                        'status' => $wasNewCase ? 'created_case' : 'updated_case',
                        'message' => $wasNewCase
                            ? 'OS ' . $case->os_number . ' criada automaticamente e quota adicionada.'
                            : 'Quota adicionada na OS existente ' . $case->os_number . '.',
                        'processed_at' => now(),
                    ]);
                });

                if (!$wasNewCase) {
                    $updatedCases++;
                }
                $createdQuotas++;
            } catch (\Throwable $e) {
                $row->update([
                    'status' => 'error',
                    'message' => 'Falha ao processar a linha: ' . Str::limit($e->getMessage(), 180, '...'),
                    'processed_at' => now(),
                ]);
                $errorRows++;
            }
        }

        $readyRows = (int) $batch->rows()->where('status', 'ready')->count();
        $pendingRows = (int) $batch->rows()->where('status', 'pending')->count();
        $batch->update([
            'status' => 'processed',
            'created_cases' => $createdCases,
            'updated_cases' => $updatedCases,
            'created_quotas' => $createdQuotas,
            'duplicate_rows' => $duplicateRows,
            'ready_rows' => $readyRows,
            'pending_rows' => $pendingRows,
            'processed_at' => now(),
            'summary_json' => array_merge($batch->summary_json ?? [], [
                'processed_created_cases' => $createdCases,
                'processed_updated_cases' => $updatedCases,
                'processed_created_quotas' => $createdQuotas,
                'processed_duplicate_rows' => $duplicateRows,
                'processed_error_rows' => $errorRows,
                'processed_pending_rows' => $pendingRows,
            ]),
        ]);

        $this->logAction($request, 'process_cobranca_import', $batch->id, 'Lote de inadimplência processado - ' . $batch->original_name);

        $message = 'Lote processado. OS criadas: ' . $createdCases . ' · OS atualizadas: ' . $updatedCases . ' · quotas adicionadas: ' . $createdQuotas;
        if ($pendingRows > 0) {
            $message .= ' · pendentes: ' . $pendingRows;
        }
        if ($duplicateRows > 0) {
            $message .= ' · duplicidades: ' . $duplicateRows;
        }
        if ($errorRows > 0) {
            $message .= ' · erros: ' . $errorRows;
        }

        return redirect()->route('cobrancas.import.show', $batch)->with('success', $message . '.');
    }

    public function importResolve(Request $request, CobrancaImportBatch $batch, CobrancaImportRow $row): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        abort_unless((int) $row->batch_id === (int) $batch->id, 404);

        if ($batch->status === 'processed') {
            return redirect()->route('cobrancas.import.show', $batch)->with('error', 'Este lote ja foi processado e nao pode mais ser alterado.');
        }

        if ($batch->status === 'cancelled') {
            return redirect()->route('cobrancas.import.show', $batch)->with('error', 'Este lote foi cancelado e nao pode mais ser alterado.');
        }

        $actionType = trim((string) $request->input('action_type', 'correct'));
        if ($actionType === 'cancel_import') {
            return $this->importCancel($request, $batch);
        }

        $details = 'Linha ' . $row->row_number . ': ';

        switch ($actionType) {
            case 'ignore_line':
                $row->update([
                    'status' => 'ignored_manual',
                    'issue_code' => 'manual_ignore',
                    'message' => 'Linha ignorada manualmente pelo usuario.',
                    'matched_case_id' => null,
                    'resolution_payload_json' => [
                        'decision' => 'ignore_line',
                        'user_id' => $user->id,
                        'decided_at' => now()->toDateTimeString(),
                    ],
                ]);
                $details .= 'linha ignorada manualmente.';
                break;

            case 'create_new_case':
                $row->update([
                    'resolution_payload_json' => [
                        'decision' => 'create_new_case',
                        'user_id' => $user->id,
                        'decided_at' => now()->toDateTimeString(),
                    ],
                ]);
                $details .= 'usuario autorizou nova OS.';
                break;

            case 'use_case':
                $caseId = (int) $request->integer('target_case_id');
                if ($caseId <= 0) {
                    return back()->with('error', 'Selecione a OS que deve receber a cota.');
                }

                $row->update([
                    'resolution_payload_json' => [
                        'decision' => 'use_case',
                        'target_case_id' => $caseId,
                        'user_id' => $user->id,
                        'decided_at' => now()->toDateTimeString(),
                    ],
                ]);
                $details .= 'usuario escolheu a OS #' . $caseId . ' para reaproveitamento.';
                break;

            case 'correct':
            default:
                $row->update([
                    'condominium_input' => trim((string) $request->input('condominium_input', $row->condominium_input)),
                    'block_input' => trim((string) $request->input('block_input', $row->block_input)),
                    'unit_input' => trim((string) $request->input('unit_input', $row->unit_input)),
                    'owner_input' => trim((string) $request->input('owner_input', $row->owner_input)),
                    'reference_input' => $this->formatImportReferenceInput((string) $request->input('reference_input', $row->reference_input)),
                    'due_date_input' => $this->formatImportDateInput((string) $request->input('due_date_input', $row->due_date_input)),
                    'amount_value' => $this->moneyToDb($request->input('amount_value', $row->amount_value)),
                    'quota_type_input' => trim((string) $request->input('quota_type_input', $row->quota_type_input)),
                    'resolution_payload_json' => [
                        'decision' => 'corrected_fields',
                        'user_id' => $user->id,
                        'decided_at' => now()->toDateTimeString(),
                    ],
                ]);
                $details .= 'campos corrigidos manualmente.';
                break;
        }

        $this->classifyImportBatch($batch);
        $this->logAction($request, 'cobrancas.import.resolve', $batch->id, $details);

        return redirect()->route('cobrancas.import.show', $batch)->with('success', 'Linha atualizada e reavaliada com sucesso.');
    }

    public function importCancel(Request $request, CobrancaImportBatch $batch): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if ($batch->status === 'processed') {
            return redirect()->route('cobrancas.import.show', $batch)->with('error', 'Este lote ja foi processado e nao pode mais ser cancelado.');
        }

        if ($batch->status === 'cancelled') {
            return redirect()->route('cobrancas.import.show', $batch)->with('success', 'Este lote ja estava cancelado.');
        }

        $batch->update([
            'status' => 'cancelled',
            'summary_json' => array_merge($batch->summary_json ?? [], [
                'cancelled_at' => now()->toDateTimeString(),
                'cancelled_by' => $user->id,
            ]),
        ]);

        $this->logAction($request, 'cobrancas.import.cancel', $batch->id, 'Importacao de inadimplencia cancelada antes do processamento.');

        return redirect()->route('cobrancas.import.show', $batch)->with('success', 'Importacao cancelada. Nenhuma OS foi criada.');
    }

    public function importReport(Request $request, CobrancaImportBatch $batch, string $format): Response|BinaryFileResponse
    {
        $format = strtolower(trim($format));
        $rows = $this->buildImportReportRows($batch);
        $headers = array_keys($rows[0] ?? [
            'Linha' => '',
            'Status' => '',
            'Condominio' => '',
            'Bloco' => '',
            'Unidade' => '',
            'Proprietario' => '',
            'Referencia' => '',
            'Vencimento' => '',
            'Valor' => '',
            'Tipo da cota' => '',
            'OS' => '',
            'Detalhe' => '',
        ]);

        $filenameBase = 'relatorio-importacao-inadimplencia-lote-' . $batch->id;

        if ($format === 'csv') {
            $this->logAction($request, 'cobrancas.import.report', $batch->id, 'Exportou relatorio final da importacao em CSV.');
            return response($this->buildCsvContent($headers, $rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.csv"',
            ]);
        }

        if ($format === 'xlsx') {
            $this->logAction($request, 'cobrancas.import.report', $batch->id, 'Exportou relatorio final da importacao em XLSX.');
            return $this->downloadSimpleXlsx($filenameBase . '.xlsx', 'Relatorio Importacao', $headers, array_map('array_values', $rows));
        }

        if ($format === 'pdf') {
            $this->logAction($request, 'cobrancas.import.report', $batch->id, 'Exportou relatorio final da importacao em PDF.');
            return $this->renderImportReportPdfResponse($batch, $this->importBatchSummary($batch), $rows);
        }

        abort(404);
    }

    private function importPreviewV2(Request $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $file = $request->file('spreadsheet');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return back()->with('error', 'Selecione uma planilha .xls ou .xlsx para importar.');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            return back()->with('error', 'Formato invalido. Use apenas .xls ou .xlsx.');
        }

        $dir = storage_path('app/cobrancas-imports');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return back()->with('error', 'Nao foi possivel preparar a pasta de importacoes.');
        }

        $storedName = now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension;
        $file->move($dir, $storedName);
        $fullPath = $dir . DIRECTORY_SEPARATOR . $storedName;

        try {
            $parsed = $this->parseImportSpreadsheet($fullPath);
            $mappedRows = $this->mapSpreadsheetRowsToImport(
                $parsed['headers'] ?? [],
                $parsed['rows'] ?? [],
                (int) ($parsed['header_row_index'] ?? 1)
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Nao foi possivel analisar a planilha: ' . $e->getMessage());
        }

        if ($mappedRows === []) {
            return back()->with('error', 'A planilha nao contem linhas validas para importar.');
        }

        $batch = DB::transaction(function () use ($user, $file, $storedName, $parsed, $mappedRows) {
            /** @var CobrancaImportBatch $batch */
            $batch = CobrancaImportBatch::query()->create([
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 255, ''),
                'stored_name' => $storedName,
                'sheet_name' => Str::limit((string) ($parsed['sheet_name'] ?? 'Planilha 1'), 180, ''),
                'file_extension' => strtolower((string) $file->getClientOriginalExtension()),
                'status' => 'parsed',
                'uploaded_by' => $user->id,
            ]);

            foreach ($mappedRows as $row) {
                CobrancaImportRow::query()->create([
                    'batch_id' => $batch->id,
                    'row_number' => $row['row_number'],
                    'raw_payload_json' => $row['raw_payload_json'],
                    'condominium_input' => $row['condominium_input'],
                    'block_input' => $row['block_input'],
                    'unit_input' => $row['unit_input'],
                    'owner_input' => $row['owner_input'],
                    'reference_input' => $row['reference_input'],
                    'due_date_input' => $row['due_date_input'],
                    'amount_value' => $row['amount_value'],
                    'quota_type_input' => $row['quota_type_input'],
                    'status' => 'error_required',
                ]);
            }

            return $batch;
        });

        $this->classifyImportBatch($batch);
        $batch->refresh();

        $summary = $batch->summary_json ?? [];
        $this->logAction(
            $request,
            'cobrancas.import.preview',
            $batch->id,
            'Pre-analise da importacao de inadimplencia. Arquivo: ' . $batch->original_name . '. Linhas lidas: ' . ($summary['total_rows'] ?? $batch->total_rows) . '.'
        );

        $message = 'Planilha analisada. Revise a previa, corrija inconsistencias e confirme apenas o que estiver seguro.';
        if ((int) ($summary['blocking_rows'] ?? 0) > 0) {
            $message .= ' Existem ' . (int) $summary['blocking_rows'] . ' linha(s) com conflito ou erro impeditivo.';
        }

        return redirect()->route('cobrancas.import.show', $batch)->with('success', $message);
    }

    private function importShowV2(Request $request, CobrancaImportBatch $batch): View
    {
        $batch->load('user');

        $filters = [
            'status' => trim((string) $request->input('status', '')),
            'q' => trim((string) $request->input('q', '')),
        ];

        $rowsQuery = $batch->rows()
            ->with([
                'unit.condominium',
                'unit.block',
                'unit.owner',
                'cobrancaCase.creator',
            ])
            ->orderBy('row_number');

        if ($filters['status'] !== '' && $filters['status'] !== 'all') {
            $rowsQuery->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $rowsQuery->where(function ($query) use ($term) {
                $query->where('condominium_input', 'like', '%' . $term . '%')
                    ->orWhere('block_input', 'like', '%' . $term . '%')
                    ->orWhere('unit_input', 'like', '%' . $term . '%')
                    ->orWhere('owner_input', 'like', '%' . $term . '%')
                    ->orWhere('reference_input', 'like', '%' . $term . '%')
                    ->orWhere('message', 'like', '%' . $term . '%');
            });
        }

        $rows = $rowsQuery->paginate(80)->withQueryString();
        $summary = $this->importBatchSummary($batch);
        $blockingRowsCount = (int) ($summary['blocking_rows'] ?? 0);
        $readyRowsCount = (int) ($summary['ready_rows'] ?? 0);
        $canProcessBatch = $batch->status !== 'processed'
            && $batch->status !== 'cancelled'
            && $readyRowsCount > 0
            && $blockingRowsCount === 0;

        return view('pages.cobrancas.import', [
            'title' => 'Importação de inadimplência',
            'batch' => $batch,
            'rows' => $rows,
            'summary' => $summary,
            'statusOptions' => $this->importStatusLabels(),
            'statusStyles' => $this->importStatusStyles(),
            'filters' => $filters,
            'recentBatches' => CobrancaImportBatch::query()->where('id', '<>', $batch->id)->latest('id')->limit(8)->get(),
            'canProcessBatch' => $canProcessBatch,
            'blockingRowsCount' => $blockingRowsCount,
            'processedSummary' => $summary['processed'] ?? [],
        ]);
    }

    private function importProcessV2(Request $request, CobrancaImportBatch $batch): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if ($batch->status === 'processed') {
            return redirect()->route('cobrancas.import.show', $batch)->with('success', 'Este lote ja foi processado anteriormente.');
        }

        if ($batch->status === 'cancelled') {
            return redirect()->route('cobrancas.import.show', $batch)->with('error', 'Este lote foi cancelado e nao pode mais ser processado.');
        }

        $this->classifyImportBatch($batch);
        $batch->refresh()->load(['rows.unit.owner']);

        $summary = $this->importBatchSummary($batch);
        if ((int) ($summary['blocking_rows'] ?? 0) > 0) {
            return redirect()
                ->route('cobrancas.import.show', $batch)
                ->with('error', 'Ainda existem linhas com conflito ou erro impeditivo. Resolva ou ignore essas linhas antes de concluir a importacao.');
        }

        $processableStatuses = ['ready_create', 'ready_link'];
        $processableRows = $batch->rows->whereIn('status', $processableStatuses)->values();
        if ($processableRows->isEmpty()) {
            return redirect()->route('cobrancas.import.show', $batch)->with('error', 'Nenhuma linha esta pronta para criar ou vincular cotas.');
        }

        $createdCases = 0;
        $linkedCaseIds = [];
        $createdQuotas = 0;
        $ignoredDuplicates = 0;
        $errorRows = 0;
        $caseCacheByUnit = [];

        foreach ($processableRows as $row) {
            $reference = $this->normalizeReferenceLabel((string) $row->reference_input);
            $dueDate = $this->normalizeImportDate((string) $row->due_date_input);
            $amount = $row->amount_value !== null ? (float) $row->amount_value : null;
            $quotaType = $this->normalizeQuotaKind($row->quota_type_input);

            if (!$row->matched_unit_id || !$row->unit || !$row->unit->owner || !$this->isValidReferenceLabel($reference) || $dueDate === null || $amount === null) {
                $row->update([
                    'status' => 'processed_error',
                    'message' => 'Linha perdeu dados essenciais antes do processamento. Revise a previa.',
                    'processed_at' => now(),
                ]);
                $errorRows++;
                continue;
            }

            try {
                $case = null;
                $createdNewCase = false;
                $unitId = (int) $row->matched_unit_id;

                if ($row->status === 'ready_link' && $row->matched_case_id) {
                    $case = CobrancaCase::query()->with(['quotas'])->find($row->matched_case_id);
                    if (!$case || !$this->isCaseReusableForImport($case)) {
                        $case = null;
                    }
                }

                $duplicateCases = $this->findDuplicateCasesForQuota((int) $row->matched_unit_id, $reference, $dueDate, $amount, $quotaType);
                if ($duplicateCases !== []) {
                    $duplicateCase = $duplicateCases[0];
                    $row->update([
                        'matched_case_id' => $duplicateCase->id,
                        'status' => 'processed_duplicate_skip',
                        'message' => 'A cota ja existe na OS ' . $duplicateCase->os_number . '. A linha foi ignorada no processamento final.',
                        'processed_at' => now(),
                    ]);
                    $ignoredDuplicates++;
                    $this->logAction(
                        $request,
                        'cobrancas.import.process',
                        $duplicateCase->id,
                        'Lote #' . $batch->id . ' · linha ' . $row->row_number . ' ignorada por duplicidade exata na OS ' . $duplicateCase->os_number . '.'
                    );
                    continue;
                }

                if (!$case) {
                    if (isset($caseCacheByUnit[$unitId])) {
                        $cachedCase = CobrancaCase::query()->with(['quotas'])->find((int) $caseCacheByUnit[$unitId]);
                        if ($cachedCase && $this->isCaseReusableForImport($cachedCase)) {
                            $case = $cachedCase;
                        }
                    }

                    if (!$case) {
                        $candidates = $this->findReusableCasesForUnit($unitId);

                        if ($row->matched_case_id) {
                            foreach ($candidates as $candidate) {
                                if ((int) $candidate->id === (int) $row->matched_case_id) {
                                    $case = $candidate;
                                    break;
                                }
                            }
                        }

                        if (!$case && count($candidates) === 1) {
                            $case = $candidates[0];
                        }
                    }

                    if (!$case) {
                        $case = DB::transaction(function () use ($row, $batch, $request, $user) {
                            return $this->createCaseFromImportRow($row, $batch, $request, $user->id);
                        });
                        $createdNewCase = true;
                        $createdCases++;
                    }
                }

                DB::transaction(function () use ($row, $case, $reference, $dueDate, $amount, $quotaType, $request, $createdNewCase, $user) {
                    CobrancaCaseQuota::query()->create([
                        'cobranca_case_id' => $case->id,
                        'reference_label' => $reference,
                        'due_date' => $dueDate,
                        'original_amount' => $amount,
                        'updated_amount' => $amount,
                        'status' => $quotaType,
                        'notes' => 'Importado via lote #' . $row->batch_id . ' (linha ' . $row->row_number . '). Origem: importacao de inadimplencia.',
                        'created_at' => now(),
                    ]);

                    $description = $createdNewCase
                        ? 'OS aberta automaticamente para a referencia ' . $reference . ' via importacao de inadimplencia.'
                        : 'Nova cota ' . $reference . ' incluida na OS existente via importacao de inadimplencia.';

                    $this->recordTimeline($case, 'import', $description, $request, now());

                    $row->update([
                        'matched_case_id' => $case->id,
                        'status' => $createdNewCase ? 'processed_created' : 'processed_linked',
                        'message' => $createdNewCase
                            ? 'OS ' . $case->os_number . ' criada e cota incluida com sucesso.'
                            : 'Cota incluida com sucesso na OS ' . $case->os_number . '.',
                        'processed_at' => now(),
                        'resolution_payload_json' => array_merge(is_array($row->resolution_payload_json) ? $row->resolution_payload_json : [], [
                            'processed_by' => $user->id,
                            'processed_at' => now()->toDateTimeString(),
                        ]),
                    ]);
                });

                if (!$createdNewCase) {
                    $linkedCaseIds[(int) $case->id] = true;
                }
                $caseCacheByUnit[$unitId] = (int) $case->id;
                $createdQuotas++;

                $this->logAction(
                    $request,
                    'cobrancas.import.process',
                    $case->id,
                    'Lote #' . $batch->id . ' · linha ' . $row->row_number . ' · referencia ' . $reference . ' · vencimento ' . $dueDate . ' · valor ' . number_format($amount, 2, '.', '') . ' · ' . ($createdNewCase ? 'OS criada' : 'cota vinculada em OS existente') . ' ' . $case->os_number . '.'
                );
            } catch (\Throwable $e) {
                $row->update([
                    'status' => 'processed_error',
                    'message' => 'Falha ao processar a linha: ' . Str::limit($e->getMessage(), 180, '...'),
                    'processed_at' => now(),
                ]);
                $errorRows++;
            }
        }

        $linkedCases = count($linkedCaseIds);

        $this->recalculateImportBatchSummary($batch->fresh('rows'), [
            'status' => 'processed',
            'processed_at' => now(),
            'created_cases' => $createdCases,
            'updated_cases' => $linkedCases,
            'created_quotas' => $createdQuotas,
            'duplicate_rows' => $ignoredDuplicates,
            'summary_json' => array_merge($batch->summary_json ?? [], [
                'processed' => [
                    'created_cases' => $createdCases,
                    'linked_cases' => $linkedCases,
                    'created_quotas' => $createdQuotas,
                    'ignored_duplicates' => $ignoredDuplicates,
                    'error_rows' => $errorRows,
                ],
            ]),
        ]);

        $this->logAction(
            $request,
            'cobrancas.import.process',
            $batch->id,
            'Lote processado. OS criadas: ' . $createdCases . '. OS reaproveitadas: ' . $linkedCases . '. Cotas criadas: ' . $createdQuotas . '. Duplicidades ignoradas: ' . $ignoredDuplicates . '.'
        );

        return redirect()
            ->route('cobrancas.import.show', $batch)
            ->with('success', 'Importacao concluida. OS criadas: ' . $createdCases . ' · OS reaproveitadas: ' . $linkedCases . ' · cotas adicionadas: ' . $createdQuotas . '.');
    }

    public function create(): View
    {
        return view('pages.cobrancas.form', array_merge([
            'title' => 'Nova OS de cobrança',
            'case' => null,
            'action' => route('cobrancas.store'),
            'submitLabel' => 'Cadastrar OS',
            'formData' => $this->formData(),
            'formRepeater' => $this->defaultRepeaterData(),
        ], $this->formDependencies()));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        [$payload, $errors, $snapshots, $repeaters] = $this->payloadFromRequest($request, null);
        if ($errors !== []) {
            return back()->withInput()->with('error', implode(' ', $errors));
        }

        $case = DB::transaction(function () use ($payload, $user, $request, $snapshots, $repeaters) {
            $chargeDate = $payload['calc_base_date'] ?: now()->toDateString();
            $year = (int) date('Y', strtotime((string) $chargeDate));
            $seq = (int) CobrancaCase::query()->where('charge_year', $year)->max('charge_seq') + 1;
            $payload['charge_year'] = $year;
            $payload['charge_seq'] = $seq;
            $payload['os_number'] = sprintf('COB-%d-%05d', $year, $seq);
            $payload['created_by'] = $user->id;
            $payload['updated_by'] = $user->id;
            $payload['last_progress_at'] = now();

            /** @var CobrancaCase $case */
            $case = CobrancaCase::query()->create($payload);
            $this->syncChildren($case, $repeaters);
            $this->recordTimeline($case, 'system', 'OS criada no módulo de cobrança.', $request, now());
            $this->recordTimeline($case, 'snapshot', 'Snapshot do devedor definido: ' . ($snapshots['debtor_summary'] ?: 'não informado') . '.', $request, now());
            $this->logAction($request, 'create_cobranca_case', $case->id, 'Cadastro de nova OS de cobrança - ' . $case->os_number);

            return $case;
        });

        return redirect()->route('cobrancas.show', $case)->with('success', 'OS de cobrança criada com sucesso.');
    }

    public function show(CobrancaCase $cobranca): View
    {
        $boletoRequestStorageReady = $this->boletoRequestStorageReady();
        $signatureStorageReady = $this->signatureStorageReady();
        $relations = [
            'condominium.administradora',
            'block',
            'unit.owner',
            'unit.tenant',
            'contacts',
            'quotas',
            'installments',
            'timeline',
            'attachments',
        ];
        if ($signatureStorageReady) {
            $relations = array_merge($relations, [
                'signatureRequests.signers',
                'signatureRequests.events.signer',
                'signatureRequests.creator',
                'signatureRequests.updater',
            ]);
        }
        $cobranca->load($relations);
        if ($boletoRequestStorageReady) {
            $cobranca->load(['emailHistories.sender', 'emailHistories.monetaryUpdate']);
        }
        $monetaryStorageReady = $this->monetaryUpdateStorageReady();
        if ($monetaryStorageReady) {
            $cobranca->load(['monetaryUpdates.items']);
        }

        return view('pages.cobrancas.show', [
            'title' => 'OS ' . $cobranca->os_number,
            'case' => $cobranca,
            'monetaryStorageReady' => $monetaryStorageReady,
            'n8nPayload' => $this->n8nPayload($cobranca),
            'stageLabels' => $this->workflowStageLabels(),
            'situationLabels' => $this->situationLabels(),
            'billingLabels' => $this->billingStatusLabels(),
            'entryStatusLabels' => $this->entryStatusLabels(),
            'agreementPaymentError' => $this->agreementPaymentPlanError($cobranca),
            'boletoRequestError' => $this->boletoRequestError($cobranca, $monetaryStorageReady, $boletoRequestStorageReady),
            'billingAdminEmails' => $this->billingAdminEmails($cobranca->condominium?->administradora),
            'preferredBoletoUpdateId' => $this->preferredMonetaryUpdate($cobranca)?->id,
            'boletoMailSubject' => $this->boletoMailSubject($cobranca),
            'signatureStorageReady' => $signatureStorageReady,
        ]);
    }

    public function agreementEdit(CobrancaCase $cobranca, CobrancaAgreementTermService $termService): View|RedirectResponse
    {
        $termStorageReady = $this->agreementTermStorageReady();
        $relations = [
            'condominium.syndic',
            'block',
            'unit.owner',
            'debtor',
            'contacts',
            'quotas',
            'installments',
        ];
        if ($termStorageReady) {
            $relations[] = 'agreementTerm';
        }
        $cobranca->load($relations);

        if ($paymentError = $this->agreementPaymentPlanError($cobranca)) {
            return redirect()->route('cobrancas.show', $cobranca)->with('error', $paymentError);
        }

        $draft = $termService->build($cobranca);
        $term = $termStorageReady ? $cobranca->agreementTerm : null;
        if (!$termStorageReady) {
            $draft['warnings'][] = 'A tabela de termos ainda não existe no banco. Rode a migration/SQL incremental para salvar customizações; enquanto isso, o PDF usa o rascunho automático.';
        }
        if ($term && $term->template_type !== $draft['template_type']) {
            $draft['warnings'][] = 'O tipo da OS mudou depois do último termo salvo. Recarregue o rascunho automático antes de emitir, se quiser atualizar as cláusulas.';
        }
        $ownerDocument = $this->ownerDocumentAttachment($cobranca);

        return view('pages.cobrancas.agreement.edit', [
            'title' => 'Termo de acordo - OS ' . $cobranca->os_number,
            'case' => $cobranca,
            'term' => $term,
            'termStorageReady' => $termStorageReady,
            'draft' => $draft,
            'ownerDocument' => $ownerDocument,
            'formData' => [
                'title' => old('title', $term?->title ?: $draft['title']),
                'body_text' => old('body_text', $term?->body_text ?: $draft['body_text']),
            ],
        ]);
    }

    public function agreementSave(Request $request, CobrancaCase $cobranca, CobrancaAgreementTermService $termService): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if (!$this->agreementTermStorageReady()) {
            return back()->withInput()->with('error', 'A tabela de termos de acordo ainda não existe no banco. Rode a migration 2026_04_11_000300 ou aplique o SQL database/sql/2026_04_cobranca_termos_acordo.sql antes de salvar customizações.');
        }

        $title = trim((string) $request->input('title', 'Termo de Confissão de Dívida e Acordo Extrajudicial'));
        $bodyText = trim((string) $request->input('body_text', ''));
        if ($bodyText === '') {
            return back()->withInput()->with('error', 'O texto do termo não pode ficar vazio.');
        }

        $cobranca->load([
            'condominium.syndic',
            'block',
            'unit.owner',
            'debtor',
            'contacts',
            'quotas',
            'installments',
        ]);
        if ($paymentError = $this->agreementPaymentPlanError($cobranca)) {
            return redirect()->route('cobrancas.show', $cobranca)->with('error', $paymentError);
        }
        $draft = $termService->build($cobranca);

        $term = CobrancaAgreementTerm::query()->firstOrNew(['cobranca_case_id' => $cobranca->id]);
        if (!$term->exists) {
            $term->generated_by = $user->id;
        }
        $term->fill([
            'template_type' => $draft['template_type'],
            'title' => Str::limit($title !== '' ? $title : $draft['title'], 255, ''),
            'body_text' => $bodyText,
            'payload_json' => $draft['payload'],
            'updated_by' => $user->id,
        ]);
        $term->save();

        $this->recordTimeline($cobranca, 'termo', 'Termo de acordo gerado/customizado para conferência e PDF.', $request, now());
        $this->logAction($request, 'save_cobranca_agreement_term', $cobranca->id, 'Termo de acordo salvo - ' . $cobranca->os_number);

        return redirect()->route('cobrancas.agreement.edit', $cobranca)->with('success', 'Termo de acordo salvo. Agora você pode gerar o PDF.');
    }

    public function agreementPrint(Request $request, CobrancaCase $cobranca, CobrancaAgreementTermService $termService): View|BinaryFileResponse
    {
        $termStorageReady = $this->agreementTermStorageReady();
        $relations = [
            'condominium.syndic',
            'block',
            'unit.owner',
            'debtor',
            'contacts',
            'quotas',
            'installments',
        ];
        if ($termStorageReady) {
            $relations[] = 'agreementTerm';
        }
        $cobranca->load($relations);

        if ($paymentError = $this->agreementPaymentPlanError($cobranca)) {
            abort(422, $paymentError);
        }

        $draft = $termService->build($cobranca);
        $term = $termStorageReady ? $cobranca->agreementTerm : null;
        $ownerDocument = $this->ownerDocumentAttachment($cobranca);
        if ($term) {
            $term->update(['printed_at' => now()]);
        }
        $this->logAction($request, 'print_cobranca_agreement_term', $cobranca->id, 'PDF do termo de acordo - ' . $cobranca->os_number);

        $viewData = [
            'case' => $cobranca,
            'title' => $term?->title ?: $draft['title'],
            'bodyText' => $term?->body_text ?: $draft['body_text'],
            'templateType' => $term?->template_type ?: $draft['template_type'],
            'payload' => $term?->payload_json ?: $draft['payload'],
            'ownerDocument' => $ownerDocument ? $this->ownerDocumentViewData($ownerDocument) : null,
            'autoPrint' => true,
            'pdfMode' => false,
        ];

        if ($pdfResponse = $this->agreementPdfResponse($viewData)) {
            return $pdfResponse;
        }

        return view('pages.cobrancas.agreement.document', $viewData);
    }

    private function agreementPdfResponse(array $viewData): ?BinaryFileResponse
    {
        $htmlPath = null;
        $pdfPath = null;

        try {
            $dir = storage_path('app/generated/cobranca-agreements');
            File::ensureDirectoryExists($dir);

            /** @var CobrancaCase $case */
            $case = $viewData['case'];
            $baseName = Str::slug($case->os_number ?: 'termo-acordo') . '-' . now()->format('YmdHis') . '-' . Str::random(6);
            $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
            $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

            File::put($htmlPath, view('pages.cobrancas.agreement.document', array_merge($viewData, [
                'autoPrint' => false,
                'pdfMode' => true,
            ]))->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);
                return null;
            }

            $ownerDocument = $viewData['ownerDocument'] ?? null;
            if (($ownerDocument['type'] ?? null) === 'pdf' && !empty($ownerDocument['absolute_path'])) {
                $mergedPath = $dir . DIRECTORY_SEPARATOR . $baseName . '-com-documento.pdf';
                if ($this->appendPdfAttachment($pdfPath, (string) $ownerDocument['absolute_path'], $mergedPath)) {
                    File::delete($pdfPath);
                    $pdfPath = $mergedPath;
                }
            }

            return response()
                ->file($pdfPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $case->os_number . '-termo-acordo.pdf"',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable) {
            if ($htmlPath) {
                File::delete($htmlPath);
            }
            if ($pdfPath) {
                File::delete($pdfPath);
            }

            return null;
        }
    }

    private function renderPdfWithChromium(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable([
            'chromium',
            'chromium-browser',
            'google-chrome',
            'google-chrome-stable',
        ]);
        if (!$binary) {
            return false;
        }

        $profileDir = dirname($pdfPath) . DIRECTORY_SEPARATOR . pathinfo($pdfPath, PATHINFO_FILENAME) . '-chrome-profile';
        File::ensureDirectoryExists($profileDir);

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-extensions',
                '--no-first-run',
                '--no-default-browser-check',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--print-to-pdf-no-header',
                '--user-data-dir=' . $profileDir,
                '--print-to-pdf=' . $pdfPath,
                'file://' . str_replace('\\', '/', $htmlPath),
            ], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        } finally {
            File::deleteDirectory($profileDir);
        }
    }

    private function renderPdfWithWkhtmltopdf(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable(['wkhtmltopdf']);
        if (!$binary) {
            return false;
        }

        try {
            $process = new Process([
                $binary,
                '--enable-local-file-access',
                '--encoding',
                'UTF-8',
                '--page-size',
                'A4',
                '--margin-top',
                '0',
                '--margin-right',
                '0',
                '--margin-bottom',
                '0',
                '--margin-left',
                '0',
                $htmlPath,
                $pdfPath,
            ], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        }
    }

    private function appendPdfAttachment(string $termPdfPath, string $attachmentPdfPath, string $mergedPath): bool
    {
        if (!is_file($termPdfPath) || !is_file($attachmentPdfPath)) {
            return false;
        }

        $binary = $this->availableExecutable(['pdfunite']);
        if (!$binary) {
            return false;
        }

        try {
            $process = new Process([$binary, $termPdfPath, $attachmentPdfPath, $mergedPath], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($mergedPath);
        } catch (\Throwable) {
            File::delete($mergedPath);
            return false;
        }
    }

    private function availableExecutable(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            try {
                $process = new Process([$candidate, '--version'], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // Some utilities do not expose --version consistently; fall back to PATH lookup below.
            }

            try {
                $process = new Process(['sh', '-lc', 'command -v ' . escapeshellarg($candidate)], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function ownerDocumentAttachment(CobrancaCase $case): ?ClientAttachment
    {
        $case->loadMissing('unit.owner');

        if ($case->unit_id) {
            $attachment = $this->latestAppendableClientDocument('unit', (int) $case->unit_id);
            if ($attachment) {
                return $attachment;
            }
        }

        $ownerId = $case->unit?->owner_entity_id ?: $case->debtor_entity_id;
        if ($ownerId) {
            return $this->latestAppendableClientDocument('entity', (int) $ownerId);
        }

        return null;
    }

    private function signatureStorageReady(): bool
    {
        try {
            return Schema::hasTable('document_signature_requests')
                && Schema::hasTable('document_signature_signers')
                && Schema::hasTable('document_signature_events');
        } catch (\Throwable) {
            return false;
        }
    }

    private function latestAppendableClientDocument(string $relatedType, int $relatedId): ?ClientAttachment
    {
        return ClientAttachment::query()
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->where('file_role', 'documento')
            ->latest('id')
            ->get()
            ->first(fn (ClientAttachment $attachment) => $this->isAppendableOwnerDocument($attachment));
    }

    private function isAppendableOwnerDocument(ClientAttachment $attachment): bool
    {
        $path = $this->clientAttachmentAbsolutePath($attachment);
        if (!$path || !is_file($path)) {
            return false;
        }

        return in_array($this->clientAttachmentKind($attachment), ['pdf', 'image'], true);
    }

    private function ownerDocumentViewData(ClientAttachment $attachment): array
    {
        $absolutePath = $this->clientAttachmentAbsolutePath($attachment);
        $relativePath = $attachment->publicUrl();

        return [
            'type' => $this->clientAttachmentKind($attachment),
            'title' => 'Documento do proprietário',
            'original_name' => (string) $attachment->original_name,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
        ];
    }

    private function clientAttachmentAbsolutePath(ClientAttachment $attachment): ?string
    {
        return $attachment->absolutePath();
    }

    private function clientAttachmentKind(ClientAttachment $attachment): string
    {
        $extension = strtolower((string) pathinfo((string) ($attachment->stored_name ?: $attachment->relative_path ?: $attachment->original_name), PATHINFO_EXTENSION));
        $mimeType = strtolower((string) $attachment->mime_type);

        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return 'pdf';
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true) || str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'unsupported';
    }

    private function boletoRequestStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_case_email_histories');
        } catch (\Throwable) {
            return false;
        }
    }

    private function billingAdminEmails(?ClientEntity $entity): array
    {
        return collect((array) ($entity?->cobranca_emails_json ?? []))
            ->map(function ($row) {
                if (is_array($row)) {
                    return trim((string) ($row['email'] ?? ''));
                }

                return trim((string) $row);
            })
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function boletoRequestError(CobrancaCase $case, bool $monetaryStorageReady, ?bool $storageReady = null): ?string
    {
        if (!($storageReady ?? $this->boletoRequestStorageReady())) {
            return 'Rode a migration do histórico de e-mails da OS antes de solicitar boletos.';
        }

        if ($paymentError = $this->agreementPaymentPlanError($case)) {
            return $paymentError;
        }

        if (!$monetaryStorageReady) {
            return 'A estrutura da memória de cálculo TJES ainda não existe no banco. Rode as migrations antes de solicitar boletos.';
        }

        if (!$case->condominium?->administradora) {
            return 'Vincule a administradora do condomínio antes de solicitar boletos.';
        }

        if ($this->billingAdminEmails($case->condominium->administradora) === []) {
            return 'Cadastre ao menos um e-mail do setor de cobrança na administradora vinculada ao condomínio.';
        }

        if (!$this->preferredMonetaryUpdate($case)) {
            return 'Salve ao menos uma memória de cálculo TJES antes de solicitar boletos.';
        }

        if (!AncoraBillingMail::isSmtpConfigured()) {
            return 'Configure o SMTP de cobrança em Configurações antes de solicitar boletos.';
        }

        return null;
    }

    private function preferredMonetaryUpdate(CobrancaCase $case): ?CobrancaMonetaryUpdate
    {
        if (!$this->monetaryUpdateStorageReady()) {
            return null;
        }

        $updates = $case->relationLoaded('monetaryUpdates')
            ? $case->monetaryUpdates
            : $case->monetaryUpdates()->with('items')->get();

        if (!$updates->count()) {
            return null;
        }

        return $updates->firstWhere('applied_to_case', true) ?: $updates->first();
    }

    private function boletoMailSubject(CobrancaCase $case): string
    {
        $condominium = trim((string) ($case->condominium?->name ?? 'Condomínio não vinculado'));
        $unit = trim((string) ($case->unit?->unit_number ?: ($case->unit?->unit_label ?: 'Sem unidade')));
        $subject = 'ACORDO - ' . $condominium . ' - UNIDADE ' . $unit;

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($subject, 'UTF-8')
            : strtoupper($subject);
    }

    private function boletoGreeting(): string
    {
        $hour = (int) now()->hour;

        return match (true) {
            $hour < 12 => 'Bom dia',
            $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };
    }

    private function boletoMailData(CobrancaCase $case, CobrancaMonetaryUpdate $update): array
    {
        $brand = AncoraSettings::brand();
        $smtp = AncoraBillingMail::smtp();

        $brand['logo_light_url'] = $this->absoluteMailAssetUrl(
            (string) ($brand['logo_light'] ?? '/imgs/logomarca.svg'),
            (string) ($brand['base_url'] ?? config('app.url'))
        );

        $data = [
            'brand' => $brand,
            'subject' => $this->boletoMailSubject($case),
            'greeting' => $this->boletoGreeting(),
            'condominiumName' => (string) ($case->condominium?->name ?? 'Condomínio não vinculado'),
            'unitLabel' => (string) ($case->unit?->unit_number ?: ($case->unit?->unit_label ?: 'Sem unidade')),
            'debtorName' => (string) ($case->debtor_name_snapshot ?: 'Não informado'),
            'agreementTotal' => $this->centsToMoney($this->moneyToCents($case->agreement_total ?? 0)),
            'agreementTotalWords' => ucfirst(BrazilianCurrencyFormatter::toWords((float) ($case->agreement_total ?? 0))),
            'paymentLines' => $this->boletoPaymentLines($case),
            'billingOverview' => $this->boletoBillingOverview($update),
            'quotaDetails' => $this->boletoQuotaDetails($update),
            'additionalCharges' => $this->boletoAdditionalCharges($update),
            'officeEmail' => (string) ($brand['company_email'] ?? ''),
            'officePhone' => (string) ($brand['company_phone'] ?? ''),
            'selectedUpdate' => $update,
            'from_address' => (string) ($smtp['from_address'] ?? ''),
            'from_name' => (string) ($smtp['from_name'] ?? 'Âncora Cobrança'),
        ];

        $data['html'] = view('emails.cobrancas.boleto-request', $data)->render();

        return $data;
    }

    private function boletoPaymentLines(CobrancaCase $case): array
    {
        $lines = collect();

        if ($this->moneyToCents($case->entry_amount ?? 0) > 0 && $case->entry_due_date) {
            $lines->push([
                'sort_key' => optional($case->entry_due_date)->format('Y-m-d') . '|00',
                'type' => 'entrada',
                'label' => 'Entrada',
                'due_date' => $case->entry_due_date,
                'amount' => (float) $case->entry_amount,
            ]);
        }

        foreach ($case->installments as $installment) {
            if ($this->moneyToCents($installment->amount ?? 0) <= 0 || !$installment->due_date) {
                continue;
            }

            $lines->push([
                'sort_key' => optional($installment->due_date)->format('Y-m-d') . '|' . str_pad((string) ($installment->installment_number ?? 0), 4, '0', STR_PAD_LEFT),
                'type' => (string) ($installment->installment_type ?? 'parcela'),
                'label' => trim((string) ($installment->label ?: '')),
                'due_date' => $installment->due_date,
                'amount' => (float) $installment->amount,
            ]);
        }

        $lines = $lines->sortBy('sort_key')->values();
        $singlePayment = $lines->count() === 1;

        return $lines
            ->map(function (array $line) use ($singlePayment) {
                $displayLabel = null;

                if ($singlePayment) {
                    $displayLabel = 'PARCELA ÚNICA';
                } elseif ($line['type'] === 'entrada') {
                    $displayLabel = 'Entrada';
                }

                return [
                    'due_date' => optional($line['due_date'])->format('d/m/Y'),
                    'amount' => $this->centsToMoney($this->moneyToCents($line['amount'] ?? 0)),
                    'display_label' => $displayLabel,
                ];
            })
            ->all();
    }

    private function createMonetaryEmailAttachmentSnapshot(CobrancaCase $case, CobrancaMonetaryUpdate $update): ?array
    {
        $htmlPath = null;
        $pdfPath = null;

        try {
            $case->loadMissing(['condominium', 'block', 'unit.owner', 'debtor']);
            $update->loadMissing(['items', 'generator']);

            $dir = storage_path('app/generated/cobranca-boleto-emails');
            File::ensureDirectoryExists($dir);

            $baseName = Str::slug(($case->os_number ?: 'os') . '-boleto-' . $update->id)
                . '-' . now()->format('YmdHis')
                . '-' . Str::random(6);

            $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
            $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

            File::put($htmlPath, view('pages.cobrancas.monetary.document', [
                'case' => $case,
                'update' => $update,
                'autoPrint' => false,
                'pdfMode' => true,
            ])->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);

                return null;
            }

            return [
                'absolute_path' => $pdfPath,
                'relative_path' => 'app/generated/cobranca-boleto-emails/' . basename($pdfPath),
                'original_name' => ($case->os_number ?: 'os') . '-memoria-calculo-tjes.pdf',
                'stored_name' => basename($pdfPath),
                'mime_type' => 'application/pdf',
                'file_size' => (int) (@filesize($pdfPath) ?: 0),
            ];
        } catch (\Throwable) {
            if ($htmlPath) {
                File::delete($htmlPath);
            }
            if ($pdfPath) {
                File::delete($pdfPath);
            }

            return null;
        }
    }

    private function boletoBillingOverview(CobrancaMonetaryUpdate $update): array
    {
        $payload = (array) ($update->payload_json ?? []);
        $settings = (array) data_get($payload, 'settings', []);
        $totals = (array) data_get($payload, 'totals_cents', []);

        $quotaCount = (int) data_get($totals, 'quota_count', $update->items()->count());
        $indexLabel = (string) data_get($settings, 'index_label', $update->index_code ?: 'Indice do TJES');
        $interestType = (string) data_get($settings, 'interest_type', $update->interest_type ?: 'legal');
        $interestRate = (float) data_get($settings, 'interest_rate_monthly', $update->interest_rate_monthly ?: 0);
        $finePercent = (float) data_get($settings, 'fine_percent', $update->fine_percent ?: 0);

        return [
            [
                'label' => 'Memoria TJES',
                'value' => 'Base em ' . (optional($update->final_date)->format('d/m/Y') ?: 'nao informada'),
            ],
            [
                'label' => 'Indice',
                'value' => $indexLabel,
            ],
            [
                'label' => 'Cotas atualizadas',
                'value' => $quotaCount . ' cota(s)',
            ],
            [
                'label' => 'Juros e multa',
                'value' => $this->boletoInterestAndFineLabel($interestType, $interestRate, $finePercent),
            ],
        ];
    }

    private function boletoQuotaDetails(CobrancaMonetaryUpdate $update): array
    {
        $update->loadMissing('items');

        return $update->items
            ->map(function (CobrancaMonetaryUpdateItem $item) {
                $referenceLabel = trim((string) ($item->reference_label ?: ''));
                if ($referenceLabel === '') {
                    $referenceLabel = 'Cota ' . (optional($item->due_date)->format('m/Y') ?: 'sem referencia');
                }

                $originalCents = $this->moneyToCents($item->original_amount ?? 0);
                $correctedCents = $this->moneyToCents($item->corrected_amount ?? 0);
                $interestCents = $this->moneyToCents($item->interest_amount ?? 0);
                $fineCents = $this->moneyToCents($item->fine_amount ?? 0);
                $totalCents = $this->moneyToCents($item->total_amount ?? 0);
                $correctionGainCents = max(0, $correctedCents - $originalCents);

                $notes = array_filter([
                    'Original ' . $this->centsToMoney($originalCents),
                    $correctionGainCents > 0 ? 'Atualizacao ' . $this->centsToMoney($correctionGainCents) : null,
                    $interestCents > 0 ? 'Juros ' . $this->centsToMoney($interestCents) : null,
                    $fineCents > 0 ? 'Multa ' . $this->centsToMoney($fineCents) : null,
                ]);

                return [
                    'label' => $referenceLabel,
                    'due_date' => optional($item->due_date)->format('d/m/Y'),
                    'amount' => $this->centsToMoney($totalCents),
                    'note' => implode(' · ', $notes),
                ];
            })
            ->values()
            ->all();
    }

    private function boletoAdditionalCharges(CobrancaMonetaryUpdate $update): array
    {
        $payload = (array) ($update->payload_json ?? []);
        $settings = (array) data_get($payload, 'settings', []);

        $costsOriginalCents = (int) data_get($payload, 'settings.costs_cents', $this->moneyToCents($update->costs_amount ?? 0));
        $costsCorrectedCents = $this->boletoUpdateCents($update, 'costs_corrected_amount', 'totals_cents.costs_corrected_cents');
        $boletoFeeCents = $this->boletoUpdateCents($update, 'boleto_fee_total', 'totals_cents.boleto_fee_cents');
        $boletoCancellationFeeCents = $this->boletoUpdateCents($update, 'boleto_cancellation_fee_total', 'totals_cents.boleto_cancellation_fee_cents');
        $abatementCents = $this->boletoUpdateCents($update, 'abatement_amount', 'totals_cents.abatement_cents');
        $attorneyFeeCents = $this->boletoUpdateCents($update, 'attorney_fee_amount', 'totals_cents.attorney_fee_cents');
        $grandTotalCents = $this->boletoUpdateCents($update, 'grand_total', 'totals_cents.grand_total_cents');

        $rows = [];

        if ($attorneyFeeCents > 0) {
            $rows[] = [
                'label' => $this->boletoAttorneyFeeLabel($update, $settings),
                'amount' => $this->centsToMoney($attorneyFeeCents),
                'tone' => 'default',
            ];
        }

        if ($costsCorrectedCents > 0) {
            $note = null;
            if ($costsOriginalCents > 0 && $costsOriginalCents !== $costsCorrectedCents) {
                $note = 'Valor base ' . $this->centsToMoney($costsOriginalCents);
                if ($update->costs_date) {
                    $note .= ' em ' . $update->costs_date->format('d/m/Y');
                }
            } elseif ($update->costs_date) {
                $note = 'Lancadas em ' . $update->costs_date->format('d/m/Y');
            }

            $rows[] = [
                'label' => 'Custas processuais',
                'amount' => $this->centsToMoney($costsCorrectedCents),
                'tone' => 'default',
                'note' => $note,
            ];
        }

        if ($boletoFeeCents > 0) {
            $rows[] = [
                'label' => 'Taxa de boleto',
                'amount' => $this->centsToMoney($boletoFeeCents),
                'tone' => 'default',
            ];
        }

        if ($boletoCancellationFeeCents > 0) {
            $rows[] = [
                'label' => 'Taxa de cancelamento de boleto',
                'amount' => $this->centsToMoney($boletoCancellationFeeCents),
                'tone' => 'default',
            ];
        }

        if ($abatementCents > 0) {
            $rows[] = [
                'label' => 'Abatimento',
                'amount' => '- ' . $this->centsToMoney($abatementCents),
                'tone' => 'deduction',
            ];
        }

        $rows[] = [
            'label' => 'Total geral do acordo',
            'amount' => $this->centsToMoney($grandTotalCents),
            'tone' => 'total',
        ];

        return $rows;
    }

    private function boletoUpdateCents(CobrancaMonetaryUpdate $update, string $attribute, string $payloadPath): int
    {
        $attributeValue = $update->{$attribute} ?? null;
        if ($attributeValue !== null && $attributeValue !== '') {
            return $this->moneyToCents($attributeValue);
        }

        return (int) data_get((array) ($update->payload_json ?? []), $payloadPath, 0);
    }

    private function boletoInterestAndFineLabel(string $interestType, float $interestRate, float $finePercent): string
    {
        $interestLabel = match ($interestType) {
            'contractual' => 'Juros contratuais de ' . number_format($interestRate, 2, ',', '.') . '% ao mes',
            'none' => 'Sem juros de mora',
            default => 'Juros legais',
        };

        if ($finePercent <= 0) {
            return $interestLabel;
        }

        return $interestLabel . ' + multa de ' . number_format($finePercent, 2, ',', '.') . '%';
    }

    private function boletoAttorneyFeeLabel(CobrancaMonetaryUpdate $update, array $settings): string
    {
        $type = (string) ($update->attorney_fee_type ?: data_get($settings, 'attorney_fee_type', 'percent'));
        $value = $update->attorney_fee_value;
        if ($value === null || $value === '') {
            $value = data_get($settings, 'attorney_fee_value', 0);
        }

        return match ($type) {
            'fixed' => 'Honorarios advocaticios fixos',
            'percent' => 'Honorarios advocaticios (' . number_format((float) $value, 2, ',', '.') . '%)',
            default => 'Honorarios advocaticios',
        };
    }

    private function resolveStoredAttachmentPath(string $relativePath): ?string
    {
        $relativePath = ltrim(trim($relativePath), '/\\');
        if ($relativePath === '') {
            return null;
        }

        $storagePath = storage_path($relativePath);
        if (is_file($storagePath)) {
            return $storagePath;
        }

        $storageAppPath = storage_path('app/' . $relativePath);
        if (is_file($storageAppPath)) {
            return $storageAppPath;
        }

        $publicPath = public_path($relativePath);
        if (is_file($publicPath)) {
            return $publicPath;
        }

        return null;
    }

    private function absoluteMailAssetUrl(string $path, string $baseUrl): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim($baseUrl !== '' ? $baseUrl : (string) config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    private function formatEmailList(array $emails): string
    {
        return implode(', ', array_values(array_filter(array_map('trim', $emails), fn ($email) => $email !== '')));
    }

    private function agreementTermStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_agreement_terms');
        } catch (\Throwable) {
            return false;
        }
    }

    private function agreementPaymentPlanError(CobrancaCase $case): ?string
    {
        $agreementTotal = $this->moneyToCents($case->agreement_total ?? 0);
        if ($agreementTotal <= 0) {
            return 'Informe o valor total do acordo antes de gerar o termo.';
        }

        $covered = 0;
        $validPayments = 0;

        if ($this->moneyToCents($case->entry_amount ?? 0) > 0) {
            if (!$case->entry_due_date) {
                return 'Informe o vencimento da entrada antes de gerar o termo.';
            }
            $covered += $this->moneyToCents($case->entry_amount);
            $validPayments++;
        }

        foreach ($case->installments as $index => $installment) {
            $amount = $this->moneyToCents($installment->amount ?? 0);
            $hasDueDate = !empty($installment->due_date);
            $label = $installment->label ?: 'parcela ' . ($index + 1);

            if ($amount <= 0 && !$hasDueDate) {
                continue;
            }
            if ($amount > 0 && !$hasDueDate) {
                return 'Informe o vencimento da ' . $label . ' antes de gerar o termo.';
            }
            if ($hasDueDate && $amount <= 0) {
                return 'Informe o valor da ' . $label . ' antes de gerar o termo.';
            }

            $covered += $amount;
            $validPayments++;
        }

        if ($validPayments === 0) {
            return 'Cadastre ao menos uma data de vencimento e um valor de pagamento antes de gerar o termo.';
        }

        $difference = $agreementTotal - $covered;
        if ($difference === 0) {
            return null;
        }

        if ($difference > 0) {
            return 'O plano de pagamento está incompleto. Faltam ' . $this->centsToMoney($difference) . ' para fechar o valor total do acordo antes de gerar o termo.';
        }

        return 'O plano de pagamento excede o valor do acordo em ' . $this->centsToMoney(abs($difference)) . '. Ajuste antes de gerar o termo.';
    }

    public function monetaryPreview(Request $request, CobrancaCase $cobranca, CobrancaMonetaryUpdateService $service): JsonResponse
    {
        if (!$this->monetaryUpdateStorageReady()) {
            return response()->json([
                'message' => 'A estrutura de atualização monetária ainda não existe no banco. Rode as migrations antes de simular.',
            ], 409);
        }

        try {
            $cobranca->load('quotas');
            $calculation = $service->calculate($cobranca->loadMissing('condominium'), $this->monetaryOptionsFromRequest($request, $cobranca));

            return response()->json($service->formatPreview($calculation));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function monetaryStore(Request $request, CobrancaCase $cobranca, CobrancaMonetaryUpdateService $service): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if (!$this->monetaryUpdateStorageReady()) {
            return back()->with('error', 'A estrutura de atualização monetária ainda não existe no banco. Rode a migration 2026_04_15_000400 antes de salvar cálculos.');
        }

        try {
            $cobranca->load('quotas');
            $calculation = $service->calculate($cobranca->loadMissing('condominium'), $this->monetaryOptionsFromRequest($request, $cobranca));
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Não foi possível calcular a atualização monetária: ' . $e->getMessage());
        }

        $applyToCase = $request->boolean('apply_to_case');

        $update = DB::transaction(function () use ($cobranca, $calculation, $request, $user, $applyToCase) {
            $update = $this->persistMonetaryUpdate($cobranca, $calculation, $user->id);
            if ($applyToCase) {
                $this->applyMonetaryUpdateToCase($cobranca, $update, $user->id);
            }

            $message = 'Memória de cálculo TJES salva'
                . ($applyToCase ? ' e aplicada à OS.' : '.')
                . ' Total geral: ' . $this->centsToMoney((int) $calculation['totals']['grand_total_cents']) . '.';
            $this->recordTimeline($cobranca, 'atualizacao_tjes', $message, $request, now());
            $this->logAction($request, 'save_cobranca_monetary_update', $cobranca->id, 'Atualização monetária TJES - ' . $cobranca->os_number);

            return $update;
        });

        $success = $applyToCase
            ? 'Memória de cálculo salva e aplicada à OS. Revise o plano de pagamento antes de gerar o termo.'
            : 'Memória de cálculo salva. Você pode aplicar na OS ou gerar o PDF pelo histórico.';

        return redirect()
            ->route('cobrancas.show', $cobranca)
            ->with('success', $success . ' Total geral: R$ ' . number_format((float) $update->grand_total, 2, ',', '.'));
    }

    public function monetaryApply(Request $request, CobrancaCase $cobranca, string $update): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if (!$this->monetaryUpdateStorageReady()) {
            return back()->with('error', 'A estrutura de atualização monetária ainda não existe no banco.');
        }

        $monetaryUpdate = CobrancaMonetaryUpdate::query()->findOrFail((int) $update);
        abort_if((int) $monetaryUpdate->cobranca_case_id !== (int) $cobranca->id, 404);

        DB::transaction(function () use ($request, $cobranca, $monetaryUpdate, $user) {
            $this->applyMonetaryUpdateToCase($cobranca, $monetaryUpdate, $user->id);
            $this->recordTimeline($cobranca, 'atualizacao_tjes', 'Memória de cálculo TJES aplicada à OS. Total geral: R$ ' . number_format((float) $monetaryUpdate->grand_total, 2, ',', '.') . '.', $request, now());
            $this->logAction($request, 'apply_cobranca_monetary_update', $cobranca->id, 'Aplicação de atualização monetária TJES - ' . $cobranca->os_number);
        });

        return back()->with('success', 'Atualização aplicada à OS. Revise parcelas/vencimentos antes de gerar o termo.');
    }

    public function monetaryPdf(Request $request, CobrancaCase $cobranca, string $update): View|BinaryFileResponse
    {
        abort_unless($this->monetaryUpdateStorageReady(), 404);

        $monetaryUpdate = CobrancaMonetaryUpdate::query()->findOrFail((int) $update);
        abort_if((int) $monetaryUpdate->cobranca_case_id !== (int) $cobranca->id, 404);

        $cobranca->load(['condominium', 'block', 'unit.owner', 'debtor']);
        $monetaryUpdate->load(['items', 'generator']);
        $this->logAction($request, 'print_cobranca_monetary_update', $cobranca->id, 'PDF da memória de cálculo TJES - ' . $cobranca->os_number);

        $viewData = [
            'case' => $cobranca,
            'update' => $monetaryUpdate,
            'autoPrint' => true,
            'pdfMode' => false,
        ];

        if ($pdfResponse = $this->monetaryPdfResponse($viewData)) {
            return $pdfResponse;
        }

        return view('pages.cobrancas.monetary.document', $viewData);
    }

    public function requestBoleto(Request $request, CobrancaCase $cobranca): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $monetaryStorageReady = $this->monetaryUpdateStorageReady();
        $boletoRequestStorageReady = $this->boletoRequestStorageReady();
        $cobranca->load([
            'condominium.administradora',
            'block',
            'unit.owner',
            'unit.tenant',
            'contacts',
            'quotas',
            'installments',
        ]);

        if ($monetaryStorageReady) {
            $cobranca->load(['monetaryUpdates.items', 'monetaryUpdates.generator']);
        }

        if ($error = $this->boletoRequestError($cobranca, $monetaryStorageReady, $boletoRequestStorageReady)) {
            return redirect()
                ->route('cobrancas.show', $cobranca)
                ->with('error', $error)
                ->with('open_boleto_request_modal', true);
        }

        $selectedUpdateId = (int) $request->integer(
            'monetary_update_id',
            (int) ($this->preferredMonetaryUpdate($cobranca)?->id ?? 0)
        );

        if ($selectedUpdateId <= 0) {
            return redirect()
                ->route('cobrancas.show', $cobranca)
                ->with('error', 'Selecione a memória de cálculo TJES que será anexada ao e-mail.')
                ->with('open_boleto_request_modal', true);
        }

        /** @var CobrancaMonetaryUpdate $monetaryUpdate */
        $monetaryUpdate = CobrancaMonetaryUpdate::query()
            ->with(['items', 'generator'])
            ->findOrFail($selectedUpdateId);

        abort_if((int) $monetaryUpdate->cobranca_case_id !== (int) $cobranca->id, 404);

        $recipients = $this->billingAdminEmails($cobranca->condominium?->administradora);
        if ($recipients === []) {
            return redirect()
                ->route('cobrancas.show', $cobranca)
                ->with('error', 'Cadastre ao menos um e-mail do setor de cobrança na administradora vinculada ao condomínio.')
                ->with('open_boleto_request_modal', true);
        }

        $attachment = $this->createMonetaryEmailAttachmentSnapshot($cobranca, $monetaryUpdate);
        if (!$attachment) {
            return redirect()
                ->route('cobrancas.show', $cobranca)
                ->with('error', 'Não foi possível gerar o PDF da memória de cálculo TJES para anexar ao e-mail.')
                ->with('open_boleto_request_modal', true);
        }

        $mailData = $this->boletoMailData($cobranca, $monetaryUpdate);
        $history = CobrancaCaseEmailHistory::query()->create([
            'cobranca_case_id' => $cobranca->id,
            'cobranca_monetary_update_id' => $monetaryUpdate->id,
            'sent_by' => $user->id,
            'from_address' => $mailData['from_address'],
            'from_name' => $mailData['from_name'],
            'subject' => $mailData['subject'],
            'recipients_json' => $recipients,
            'body_html' => $mailData['html'],
            'attachment_original_name' => $attachment['original_name'],
            'attachment_stored_name' => $attachment['stored_name'],
            'attachment_relative_path' => $attachment['relative_path'],
            'attachment_mime_type' => $attachment['mime_type'],
            'attachment_file_size' => $attachment['file_size'],
            'send_status' => 'pending',
            'transport_message' => 'Envio iniciado pelo usuário ' . ($user->email ?? 'desconhecido') . '.',
            'imap_status' => 'pending',
            'imap_message' => 'Aguardando envio pelo SMTP de cobrança.',
        ]);

        $result = AncoraBillingMail::sendHtml([
            'subject' => $mailData['subject'],
            'html' => $mailData['html'],
            'to' => $recipients,
            'attachment_path' => $attachment['absolute_path'],
            'attachment_name' => $attachment['original_name'],
            'attachment_mime' => $attachment['mime_type'],
        ]);

        $sendStatus = (string) ($result['send_status'] ?? 'failed');

        DB::transaction(function () use ($history, $result, $sendStatus, $cobranca, $request, $user, $recipients, $monetaryUpdate) {
            $history->update([
                'send_status' => $sendStatus,
                'transport_message' => Str::limit((string) ($result['transport_message'] ?? ''), 65535, ''),
                'imap_status' => (string) ($result['imap_status'] ?? 'not_attempted'),
                'imap_message' => Str::limit((string) ($result['imap_message'] ?? ''), 65535, ''),
            ]);

            if ($sendStatus !== 'sent') {
                return;
            }

            $entryStatus = (string) ($cobranca->entry_status ?? '');
            if ($this->moneyToCents($cobranca->entry_amount ?? 0) > 0 && !in_array($entryStatus, ['pago', 'boleto_enviado'], true)) {
                $cobranca->forceFill([
                    'entry_status' => 'boleto_solicitado',
                    'updated_by' => $user->id,
                ])->save();
            }

            CobrancaCaseInstallment::query()
                ->where('cobranca_case_id', $cobranca->id)
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhere('status', '')
                        ->orWhere('status', 'pendente')
                        ->orWhere('status', 'boleto_solicitado');
                })
                ->update(['status' => 'boleto_solicitado']);

            $timelineDescription = 'Solicitação de boletos enviada à administradora '
                . ($cobranca->condominium?->administradora?->display_name ?: 'vinculada ao condomínio')
                . ' (' . $this->formatEmailList($recipients) . ')'
                . '. Memória TJES anexada com base em '
                . (optional($monetaryUpdate->final_date)->format('d/m/Y') ?: 'data não informada') . '.';

            $this->recordTimeline($cobranca, 'boleto', $timelineDescription, $request, now());
        });

        if ($sendStatus !== 'sent') {
            return redirect()
                ->route('cobrancas.show', $cobranca)
                ->with('error', 'Não foi possível enviar a solicitação de boleto: ' . ($result['transport_message'] ?? 'falha desconhecida') . '.')
                ->with('open_boleto_request_modal', true);
        }

        $details = 'Solicitação de boletos enviada à administradora '
            . ($cobranca->condominium?->administradora?->display_name ?: 'não informada')
            . ' para ' . $this->formatEmailList($recipients)
            . ' com anexo da memória TJES #' . $monetaryUpdate->id . '.';

        $this->logAction($request, 'request_cobranca_boleto', $cobranca->id, $details);

        $successMessage = 'Solicitação de boleto enviada para ' . count($recipients) . ' destinatário(s).';
        if (($result['imap_status'] ?? '') !== 'mirrored') {
            $successMessage .= ' Espelhamento IMAP: ' . ($result['imap_message'] ?? 'não informado') . '.';
        }

        return redirect()->route('cobrancas.show', $cobranca)->with('success', $successMessage);
    }

    public function showEmailHistory(CobrancaCase $cobranca, CobrancaCaseEmailHistory $history): Response
    {
        abort_if((int) $history->cobranca_case_id !== (int) $cobranca->id, 404);

        $html = trim((string) $history->body_html);
        if ($html === '') {
            $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Espelho de e-mail</title></head><body><p>Este registro não possui conteúdo HTML salvo.</p></body></html>';
        }

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function downloadEmailHistoryAttachment(CobrancaCase $cobranca, CobrancaCaseEmailHistory $history): BinaryFileResponse
    {
        abort_if((int) $history->cobranca_case_id !== (int) $cobranca->id, 404);

        $path = $this->resolveStoredAttachmentPath((string) $history->attachment_relative_path);
        abort_unless($path && is_file($path), 404);

        return response()->download(
            $path,
            $history->attachment_original_name ?: basename($path),
            ['Content-Type' => $history->attachment_mime_type ?: 'application/octet-stream']
        );
    }

    private function monetaryPdfResponse(array $viewData): ?BinaryFileResponse
    {
        $htmlPath = null;
        $pdfPath = null;

        try {
            $dir = storage_path('app/generated/cobranca-monetary-updates');
            File::ensureDirectoryExists($dir);

            /** @var CobrancaCase $case */
            $case = $viewData['case'];
            /** @var CobrancaMonetaryUpdate $update */
            $update = $viewData['update'];
            $baseName = Str::slug(($case->os_number ?: 'os') . '-memoria-calculo-' . $update->id) . '-' . now()->format('YmdHis') . '-' . Str::random(6);
            $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
            $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

            File::put($htmlPath, view('pages.cobrancas.monetary.document', array_merge($viewData, [
                'autoPrint' => false,
                'pdfMode' => true,
            ]))->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);
                return null;
            }

            return response()
                ->file($pdfPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $case->os_number . '-memoria-calculo-tjes.pdf"',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable) {
            if ($htmlPath) {
                File::delete($htmlPath);
            }
            if ($pdfPath) {
                File::delete($pdfPath);
            }

            return null;
        }
    }

    private function persistMonetaryUpdate(CobrancaCase $case, array $calculation, int $userId): CobrancaMonetaryUpdate
    {
        $settings = $calculation['settings'];
        $totals = $calculation['totals'];

        $payload = [
            'cobranca_case_id' => $case->id,
            'index_code' => $settings['index_code'],
            'calculation_date' => $settings['calculation_date'],
            'final_date' => $settings['final_date']->toDateString(),
            'interest_type' => $settings['interest_type'],
            'interest_rate_monthly' => $settings['interest_type'] === 'contractual' ? $settings['interest_rate_monthly'] : null,
            'fine_percent' => $settings['fine_percent'],
            'attorney_fee_type' => $settings['attorney_fee_type'],
            'attorney_fee_value' => $settings['attorney_fee_type'] === 'fixed'
                ? $this->decimalFromCents((int) $settings['attorney_fee_value'])
                : (float) $settings['attorney_fee_value'],
            'costs_amount' => $this->decimalFromCents((int) $settings['costs_cents']),
            'costs_date' => $settings['costs_date']?->toDateString(),
            'costs_corrected_amount' => $this->decimalFromCents((int) $totals['costs_corrected_cents']),
            'abatement_amount' => $this->decimalFromCents((int) $totals['abatement_cents']),
            'original_total' => $this->decimalFromCents((int) $totals['original_cents']),
            'corrected_total' => $this->decimalFromCents((int) $totals['corrected_cents']),
            'interest_total' => $this->decimalFromCents((int) $totals['interest_cents']),
            'fine_total' => $this->decimalFromCents((int) $totals['fine_cents']),
            'debit_total' => $this->decimalFromCents((int) $totals['debit_total_cents']),
            'attorney_fee_amount' => $this->decimalFromCents((int) $totals['attorney_fee_cents']),
            'grand_total' => $this->decimalFromCents((int) $totals['grand_total_cents']),
            'payload_json' => $this->monetaryPayload($calculation),
            'generated_by' => $userId,
        ];

        if ($this->monetaryUpdateHasColumn('boleto_fee_total')) {
            $payload['boleto_fee_total'] = $this->decimalFromCents((int) $totals['boleto_fee_cents']);
        }

        if ($this->monetaryUpdateHasColumn('boleto_cancellation_fee_total')) {
            $payload['boleto_cancellation_fee_total'] = $this->decimalFromCents((int) $totals['boleto_cancellation_fee_cents']);
        }

        /** @var CobrancaMonetaryUpdate $update */
        $update = CobrancaMonetaryUpdate::query()->create($payload);

        foreach ($calculation['items'] as $item) {
            CobrancaMonetaryUpdateItem::query()->create([
                'cobranca_monetary_update_id' => $update->id,
                'cobranca_case_quota_id' => $item['quota_id'],
                'reference_label' => Str::limit((string) $item['reference_label'], 100, ''),
                'due_date' => $item['due_date']->toDateString(),
                'original_amount' => $this->decimalFromCents((int) $item['original_cents']),
                'correction_factor' => $item['correction_factor'],
                'corrected_amount' => $this->decimalFromCents((int) $item['corrected_cents']),
                'interest_months' => $item['interest_months'],
                'interest_percent' => $item['interest_percent'],
                'interest_amount' => $this->decimalFromCents((int) $item['interest_cents']),
                'fine_percent' => $item['fine_percent'],
                'fine_amount' => $this->decimalFromCents((int) $item['fine_cents']),
                'total_amount' => $this->decimalFromCents((int) $item['total_cents']),
                'created_at' => now(),
            ]);
        }

        return $update->load('items');
    }

    private function monetaryUpdateHasColumn(string $column): bool
    {
        static $cache = [];

        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        try {
            return $cache[$column] = Schema::hasColumn('cobranca_monetary_updates', $column);
        } catch (\Throwable) {
            return $cache[$column] = false;
        }
    }

    private function applyMonetaryUpdateToCase(CobrancaCase $case, CobrancaMonetaryUpdate $update, int $userId): void
    {
        $update->loadMissing('items');
        foreach ($update->items as $item) {
            if (!$item->cobranca_case_quota_id) {
                continue;
            }

            CobrancaCaseQuota::query()
                ->whereKey($item->cobranca_case_quota_id)
                ->where('cobranca_case_id', $case->id)
                ->update(['updated_amount' => $item->total_amount]);
        }

        $case->update([
            'agreement_total' => $update->grand_total,
            'fees_amount' => $update->attorney_fee_amount,
            'calc_base_date' => $update->final_date,
            'updated_by' => $userId,
        ]);

        $update->forceFill([
            'applied_to_case' => true,
            'applied_at' => now(),
            'applied_by' => $userId,
        ])->save();
    }

    private function monetaryPayload(array $calculation): array
    {
        $settings = $calculation['settings'];

        return [
            'settings' => [
                'index_code' => $settings['index_code'],
                'index_label' => $settings['index_label'],
                'calculation_date' => $settings['calculation_date'],
                'final_date' => $settings['final_date']->toDateString(),
                'interest_type' => $settings['interest_type'],
                'interest_rate_monthly' => $settings['interest_rate_monthly'],
                'fine_percent' => $settings['fine_percent'],
                'attorney_fee_type' => $settings['attorney_fee_type'],
                'attorney_fee_value' => $settings['attorney_fee_value'],
                'costs_cents' => $settings['costs_cents'],
                'costs_date' => $settings['costs_date']?->toDateString(),
                'boleto_fee_cents' => $settings['boleto_fee_cents'],
                'boleto_cancellation_fee_cents' => $settings['boleto_cancellation_fee_cents'],
                'apply_boleto_fee' => $settings['apply_boleto_fee'],
                'apply_boleto_cancellation_fee' => $settings['apply_boleto_cancellation_fee'],
                'abatement_cents' => $settings['abatement_cents'],
                'quota_ids' => $settings['quota_ids'],
            ],
            'totals_cents' => $calculation['totals'],
            'summary' => $calculation['summary'],
        ];
    }

    private function monetaryOptionsFromRequest(Request $request, CobrancaCase $case): array
    {
        $case->loadMissing('condominium');

        return [
            'final_date' => $request->input('final_date'),
            'index_code' => $request->input('index_code', 'ATM'),
            'quota_ids' => $request->input('quota_ids', []),
            'interest_type' => $request->input('interest_type', 'legal'),
            'interest_rate_monthly' => $request->input('interest_rate_monthly'),
            'fine_percent' => $request->input('fine_percent'),
            'attorney_fee_type' => $request->input('attorney_fee_type', 'percent'),
            'attorney_fee_value' => $request->input('attorney_fee_value'),
            'costs_amount' => $request->input('costs_amount'),
            'costs_date' => $request->input('costs_date'),
            'abatement_amount' => $request->input('abatement_amount'),
            'boleto_fee_amount' => $case->condominium?->boleto_fee_amount,
            'boleto_cancellation_fee_amount' => $case->condominium?->boleto_cancellation_fee_amount,
            'apply_boleto_fee' => config('automation.collection.boleto_fees.enabled', true),
            'apply_boleto_cancellation_fee' => config('automation.collection.boleto_fees.cancellation_enabled', true),
        ];
    }

    private function monetaryUpdateStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_monetary_index_factors')
                && Schema::hasTable('cobranca_monetary_updates')
                && Schema::hasTable('cobranca_monetary_update_items');
        } catch (\Throwable) {
            return false;
        }
    }

    private function decimalFromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    public function edit(CobrancaCase $cobranca): View
    {
        $cobranca->load(['contacts', 'quotas', 'installments']);

        return view('pages.cobrancas.form', array_merge([
            'title' => 'Editar OS ' . $cobranca->os_number,
            'case' => $cobranca,
            'action' => route('cobrancas.update', $cobranca),
            'submitLabel' => 'Salvar alterações',
            'formData' => $this->formData($cobranca),
            'formRepeater' => $this->repeaterDataFromCase($cobranca),
            'agreementPaymentError' => $this->agreementPaymentPlanError($cobranca),
        ], $this->formDependencies()));
    }

    public function update(Request $request, CobrancaCase $cobranca): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        [$payload, $errors, $snapshots, $repeaters] = $this->payloadFromRequest($request, $cobranca);
        if ($errors !== []) {
            return back()->withInput()->with('error', implode(' ', $errors));
        }

        DB::transaction(function () use ($payload, $cobranca, $user, $request, $snapshots, $repeaters) {
            $payload['updated_by'] = $user->id;
            $cobranca->update($payload);
            $this->syncChildren($cobranca, $repeaters);
            $this->recordTimeline($cobranca, 'update', 'Dados principais da OS atualizados. ' . ($snapshots['debtor_summary'] ? 'Devedor atual: ' . $snapshots['debtor_summary'] . '.' : ''), $request, now());
            $this->logAction($request, 'update_cobranca_case', $cobranca->id, 'Atualização da OS de cobrança - ' . $cobranca->os_number);
        });

        return redirect()->route('cobrancas.show', $cobranca)->with('success', 'OS de cobrança atualizada.');
    }

    public function destroy(Request $request, CobrancaCase $cobranca): RedirectResponse
    {
        foreach ($cobranca->attachments as $attachment) {
            $path = public_path(ltrim((string) $attachment->relative_path, '/'));
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $osNumber = $cobranca->os_number;
        $id = $cobranca->id;
        $cobranca->delete();
        $this->logAction($request, 'delete_cobranca_case', $id, 'Exclusão da OS de cobrança - ' . $osNumber);

        return redirect()->route('cobrancas.index')->with('success', 'OS excluída com sucesso.');
    }

    public function addTimeline(Request $request, CobrancaCase $cobranca): RedirectResponse
    {
        $description = trim((string) $request->input('description', ''));
        if ($description === '') {
            return back()->with('error', 'Descreva o andamento antes de salvar.');
        }

        $type = trim((string) $request->input('event_type', 'manual')) ?: 'manual';
        $this->recordTimeline($cobranca, $type, $description, $request, now());
        $this->logAction($request, 'timeline_cobranca_case', $cobranca->id, 'Novo andamento lançado na OS - ' . $cobranca->os_number);

        return back()->with('success', 'Andamento registrado.');
    }

    public function uploadAttachment(Request $request, CobrancaCase $cobranca): RedirectResponse
    {
        $files = $this->normalizeUploadedFiles($request->file('files'));
        if ($files === []) {
            return back()->with('error', 'Selecione pelo menos um arquivo para anexar.');
        }

        $role = trim((string) $request->input('file_role', 'documento')) ?: 'documento';
        $dir = public_path('uploads/cobrancas/' . $cobranca->id);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return back()->with('error', 'Não foi possível preparar a pasta de anexos desta OS.');
        }

        $uploaded = 0;
        $user = AncoraAuth::user($request);
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx'], true)) {
                continue;
            }

            $originalName = Str::limit((string) $file->getClientOriginalName(), 255, '');
            $mimeType = Str::limit((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''), 120, '');
            $fileSize = $this->safeUploadedFileSize($file);
            $stored = now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
            $file->move($dir, $stored);
            $finalPath = $dir . DIRECTORY_SEPARATOR . $stored;
            if (is_file($finalPath)) {
                $fileSize = (int) (@filesize($finalPath) ?: $fileSize);
            }

            CobrancaCaseAttachment::query()->create([
                'cobranca_case_id' => $cobranca->id,
                'file_role' => Str::limit($role, 40, ''),
                'original_name' => $originalName,
                'stored_name' => $stored,
                'relative_path' => '/uploads/cobrancas/' . $cobranca->id . '/' . $stored,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'uploaded_by' => $user?->id,
                'created_at' => now(),
            ]);
            $uploaded++;
        }

        if ($uploaded === 0) {
            return back()->with('error', 'Nenhum arquivo pôde ser anexado.');
        }

        $this->recordTimeline($cobranca, 'attachment', $uploaded . ' arquivo(s) anexado(s) ao GED.', $request, now());
        $this->logAction($request, 'upload_attachment_cobranca_case', $cobranca->id, 'Anexos enviados para a OS - ' . $cobranca->os_number);

        return back()->with('success', 'Arquivo(s) anexado(s) com sucesso.');
    }

    public function downloadAttachment(CobrancaCase $cobranca, CobrancaCaseAttachment $attachment): BinaryFileResponse
    {
        abort_if($attachment->cobranca_case_id !== $cobranca->id, 404);
        $path = public_path(ltrim((string) $attachment->relative_path, '/'));
        abort_unless(is_file($path), 404);
        return response()->download($path, $attachment->original_name);
    }

    public function deleteAttachment(Request $request, CobrancaCase $cobranca, CobrancaCaseAttachment $attachment): RedirectResponse
    {
        abort_if($attachment->cobranca_case_id !== $cobranca->id, 404);
        $path = public_path(ltrim((string) $attachment->relative_path, '/'));
        if (is_file($path)) {
            @unlink($path);
        }
        $originalName = $attachment->original_name;
        $attachment->delete();
        $this->recordTimeline($cobranca, 'attachment_delete', 'Anexo removido do GED: ' . $originalName . '.', $request, now());
        $this->logAction($request, 'delete_attachment_cobranca_case', $cobranca->id, 'Exclusão de anexo na OS - ' . $cobranca->os_number);

        return back()->with('success', 'Anexo removido.');
    }

    private function payloadFromRequest(Request $request, ?CobrancaCase $current): array
    {
        $unit = null;
        if ((int) $request->integer('unit_id') > 0) {
            $unit = ClientUnit::query()->with(['condominium', 'block', 'owner', 'tenant'])->find((int) $request->integer('unit_id'));
        } elseif ($current?->unit_id) {
            $unit = ClientUnit::query()->with(['condominium', 'block', 'owner', 'tenant'])->find((int) $current->unit_id);
        }

        $debtorRole = 'owner';
        $manual = [
            'name' => '',
            'document' => '',
            'email' => '',
            'phone' => '',
        ];

        $debtorSnapshot = $this->resolveDebtorSnapshot($unit, $debtorRole, $manual);
        $workflowStage = $this->normalizeWorkflowStage(trim((string) $request->input('workflow_stage', 'triagem')) ?: 'triagem');
        $entryStatus = trim((string) $request->input('entry_status', '')) ?: null;
        if ($entryStatus === '__custom') {
            $entryStatus = trim((string) $request->input('entry_status_custom', '')) ?: null;
        }
        $entryStatus = $entryStatus ? Str::limit($entryStatus, 40, '') : null;

        $payload = [
            'condominium_id' => $unit?->condominium_id,
            'block_id' => $unit?->block_id,
            'unit_id' => $unit?->id,
            'debtor_entity_id' => $debtorSnapshot['entity_id'],
            'debtor_role' => $debtorRole,
            'debtor_name_snapshot' => $debtorSnapshot['name'],
            'debtor_document_snapshot' => $debtorSnapshot['document'],
            'debtor_email_snapshot' => $debtorSnapshot['email'],
            'debtor_phone_snapshot' => $debtorSnapshot['phone'],
            'charge_type' => trim((string) $request->input('charge_type', 'extrajudicial')) ?: 'extrajudicial',
            'agreement_total' => $this->moneyToDb($request->input('agreement_total')),
            'billing_status' => trim((string) $request->input('billing_status', 'a_faturar')) ?: 'a_faturar',
            'billing_date' => trim((string) $request->input('billing_date', '')) ?: null,
            'alert_message' => trim((string) $request->input('alert_message', '')) ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'situation' => $this->situationFromWorkflowStage($workflowStage),
            'workflow_stage' => $workflowStage,
            'entry_status' => $entryStatus,
            'entry_due_date' => trim((string) $request->input('entry_due_date', '')) ?: null,
            'entry_amount' => $this->moneyToDb($request->input('entry_amount')),
            'fees_amount' => $this->moneyToDb($request->input('fees_amount')),
            'judicial_case_number' => trim((string) $request->input('judicial_case_number', '')) ?: null,
            'calc_base_date' => trim((string) $request->input('calc_base_date', '')) ?: null,
        ];

        $repeaters = [
            'emails' => $this->emailRowsFromRequest($request),
            'phones' => $this->phoneRowsFromRequest($request),
            'quotas' => $this->quotaRowsFromRequest($request),
            'installments' => $this->installmentRowsFromRequest($request),
        ];

        $errors = [];
        if (!$unit) {
            $errors[] = 'Selecione a unidade vinculada à OS.';
        }
        if ($payload['debtor_name_snapshot'] === '') {
            $errors[] = 'A unidade selecionada precisa ter um proprietário vinculado para gerar a OS de cobrança.';
        }
        if (!in_array($payload['charge_type'], array_keys($this->chargeTypeLabels()), true)) {
            $errors[] = 'Selecione um tipo de cobrança válido.';
        }
        if (!in_array($payload['workflow_stage'], array_keys($this->workflowStageLabels()), true)) {
            $errors[] = 'Selecione uma situação da OS válida.';
        }
        if (!in_array($payload['billing_status'], array_keys($this->billingStatusLabels()), true)) {
            $errors[] = 'Selecione um status de faturamento válido.';
        }
        if ($payload['charge_type'] === 'judicial' && empty($payload['judicial_case_number'])) {
            $errors[] = 'Informe o número do processo para cobrança judicializada.';
        }
        if ($payload['charge_type'] === 'extrajudicial') {
            $payload['judicial_case_number'] = null;
        }
        if ($repeaters['quotas'] === []) {
            $errors[] = 'Cadastre ao menos uma quota em aberto.';
        }
        foreach ($repeaters['installments'] as $index => $installment) {
            $label = $installment['label'] ?: 'parcela ' . ($index + 1);
            if ((float) $installment['amount'] > 0 && empty($installment['due_date'])) {
                $errors[] = 'Informe o vencimento da ' . $label . ' antes de salvar.';
            }
            if (!empty($installment['due_date']) && (float) $installment['amount'] <= 0) {
                $errors[] = 'Informe o valor da ' . $label . ' antes de salvar.';
            }
        }
        if ($invalidEmails = $this->invalidNotificationEmails($request)) {
            $errors[] = 'Revise os e-mails de notificação. Informe apenas endereços válidos.';
        }

        return [$payload, $errors, ['debtor_summary' => $debtorSnapshot['summary']], $repeaters];
    }

    private function resolveDebtorSnapshot(?ClientUnit $unit, string $debtorRole, array $manual): array
    {
        $entity = null;
        if ($unit) {
            $entity = match ($debtorRole) {
                'tenant' => $unit->tenant,
                'manual' => null,
                default => $unit->owner,
            };
        }

        if ($debtorRole === 'manual' || !$entity) {
            $name = trim((string) ($manual['name'] ?? ''));
            $document = trim((string) ($manual['document'] ?? ''));
            $email = trim((string) ($manual['email'] ?? ''));
            $phone = trim((string) ($manual['phone'] ?? ''));
            return [
                'entity_id' => null,
                'name' => $name,
                'document' => $document ?: null,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'summary' => $name !== '' ? $name . ($document ? ' · ' . $document : '') : '',
            ];
        }

        $primaryEmail = collect($entity->emails_json ?? [])->first()['email'] ?? null;
        $primaryPhone = collect($entity->phones_json ?? [])->first()['number'] ?? null;
        return [
            'entity_id' => $entity->id,
            'name' => (string) $entity->display_name,
            'document' => $entity->cpf_cnpj,
            'email' => $primaryEmail,
            'phone' => $primaryPhone,
            'summary' => $entity->display_name . ($entity->cpf_cnpj ? ' · ' . $entity->cpf_cnpj : ''),
        ];
    }

    private function syncChildren(CobrancaCase $case, array $repeaters): void
    {
        CobrancaCaseContact::query()->where('cobranca_case_id', $case->id)->delete();
        CobrancaCaseQuota::query()->where('cobranca_case_id', $case->id)->delete();
        CobrancaCaseInstallment::query()->where('cobranca_case_id', $case->id)->delete();

        foreach ($repeaters['emails'] as $index => $row) {
            CobrancaCaseContact::query()->create([
                'cobranca_case_id' => $case->id,
                'contact_type' => 'email',
                'label' => $row['label'] ?: 'E-mail ' . ($index + 1),
                'value' => $row['value'],
                'is_primary' => $index === 0,
                'is_whatsapp' => false,
                'created_at' => now(),
            ]);
        }
        foreach ($repeaters['phones'] as $index => $row) {
            CobrancaCaseContact::query()->create([
                'cobranca_case_id' => $case->id,
                'contact_type' => 'phone',
                'label' => $row['label'] ?: 'Telefone ' . ($index + 1),
                'value' => $row['value'],
                'is_primary' => $index === 0,
                'is_whatsapp' => (bool) $row['is_whatsapp'],
                'created_at' => now(),
            ]);
        }
        foreach ($repeaters['quotas'] as $row) {
            CobrancaCaseQuota::query()->create([
                'cobranca_case_id' => $case->id,
                'reference_label' => $row['reference_label'],
                'due_date' => $row['due_date'],
                'original_amount' => $row['original_amount'],
                'updated_amount' => $row['updated_amount'],
                'status' => $row['status'],
                'notes' => $row['notes'],
                'created_at' => now(),
            ]);
        }
        foreach ($repeaters['installments'] as $index => $row) {
            CobrancaCaseInstallment::query()->create([
                'cobranca_case_id' => $case->id,
                'label' => $row['label'] ?: 'Parcela ' . ($index + 1),
                'installment_type' => $row['installment_type'] ?: 'parcela',
                'installment_number' => $row['installment_number'] ?: ($index + 1),
                'due_date' => $row['due_date'],
                'amount' => $row['amount'],
                'status' => $row['status'],
                'created_at' => now(),
            ]);
        }
    }

    private function recordTimeline(CobrancaCase $case, string $eventType, string $description, Request $request, $timestamp): void
    {
        $user = AncoraAuth::user($request);
        CobrancaCaseTimeline::query()->create([
            'cobranca_case_id' => $case->id,
            'event_type' => Str::limit($eventType, 40, ''),
            'description' => $description,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'created_at' => $timestamp,
        ]);

        $case->updateQuietly(['last_progress_at' => $timestamp, 'updated_by' => $user?->id]);
    }

    private function formDependencies(): array
    {
        $units = ClientUnit::query()
            ->with(['condominium', 'block', 'owner', 'tenant'])
            ->orderBy('condominium_id')
            ->orderBy('unit_number')
            ->get();

        $condominiums = ClientCondominium::query()
            ->orderBy('name')
            ->get(['id', 'name', 'has_blocks']);

        $blocks = ClientBlock::query()
            ->orderBy('condominium_id')
            ->orderBy('name')
            ->get(['id', 'condominium_id', 'name']);

        $selectorUnits = [];
        foreach ($units as $unit) {
            $blockKey = (string) ($unit->block_id ?: 0);
            $selectorUnits[(string) $unit->condominium_id][$blockKey][] = [
                'id' => (int) $unit->id,
                'label' => trim(($unit->block?->name ? $unit->block->name . ' · ' : '') . 'Unidade ' . ($unit->unit_label ?: $unit->unit_number)),
                'unit_number' => (string) ($unit->unit_label ?: $unit->unit_number),
                'block_id' => $unit->block_id ? (int) $unit->block_id : null,
                'owner_name' => (string) ($unit->owner?->display_name ?? ''),
                'owner_document' => (string) ($unit->owner?->cpf_cnpj ?? ''),
                'owner_email' => (string) ((collect($unit->owner?->emails_json ?? [])->first()['email'] ?? '')),
                'owner_phone' => (string) ((collect($unit->owner?->phones_json ?? [])->first()['number'] ?? '')),
                'owner_emails' => collect($unit->owner?->emails_json ?? [])->pluck('email')->filter()->values()->all(),
                'owner_phones' => collect($unit->owner?->phones_json ?? [])->pluck('number')->filter()->values()->all(),
            ];
        }

        $selectorBlocks = [];
        foreach ($blocks as $block) {
            $selectorBlocks[(string) $block->condominium_id][] = [
                'id' => (int) $block->id,
                'name' => (string) $block->name,
            ];
        }

        return [
            'units' => $units,
            'unitSelectorData' => [
                'condominiums' => $condominiums->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'name' => (string) $item->name,
                    'has_blocks' => (bool) $item->has_blocks,
                ])->values()->all(),
                'blocks' => $selectorBlocks,
                'units' => $selectorUnits,
            ],
            'chargeTypeLabels' => $this->chargeTypeLabels(),
            'workflowStageLabels' => $this->workflowStageLabels(),
            'situationLabels' => $this->situationLabels(),
            'billingStatusLabels' => $this->billingStatusLabels(),
            'entryStatusLabels' => $this->entryStatusLabels(),
            'quotaStatusLabels' => $this->quotaStatusLabels(),
            'installmentStatusLabels' => $this->installmentStatusLabels(),
        ];
    }

    private function formData(?CobrancaCase $case = null): array
    {
        $unit = $case?->relationLoaded('unit') ? $case->unit : ($case?->unit_id ? ClientUnit::query()->with(['owner', 'tenant'])->find($case->unit_id) : null);
        $storedWorkflowStage = $this->normalizeWorkflowStage((string) ($case?->workflow_stage ?: $this->workflowStageFromSituation($case?->situation)));
        $storedEntryStatus = (string) ($case?->entry_status ?? '');
        $knownEntryStatuses = array_keys($this->entryStatusLabels());
        $entryStatusValue = $storedEntryStatus !== '' && !in_array($storedEntryStatus, $knownEntryStatuses, true)
            ? '__custom'
            : ($storedEntryStatus ?: null);
        $entryStatusCustom = $entryStatusValue === '__custom' ? $storedEntryStatus : '';

        return [
            'unit_id' => old('unit_id', $case?->unit_id),
            'debtor_role' => 'owner',
            'manual_debtor_name' => '',
            'manual_debtor_document' => '',
            'manual_debtor_email' => '',
            'manual_debtor_phone' => '',
            'charge_type' => old('charge_type', $case?->charge_type ?: 'extrajudicial'),
            'agreement_total' => old('agreement_total', $case?->agreement_total),
            'billing_status' => old('billing_status', $case?->billing_status ?: 'a_faturar'),
            'billing_date' => old('billing_date', optional($case?->billing_date)->format('Y-m-d')),
            'alert_message' => old('alert_message', $case?->alert_message),
            'notes' => old('notes', $case?->notes),
            'situation' => old('situation', $case?->situation ?: 'processo_aberto'),
            'workflow_stage' => $this->normalizeWorkflowStage((string) old('workflow_stage', $storedWorkflowStage ?: 'triagem')),
            'entry_status' => old('entry_status', $entryStatusValue),
            'entry_status_custom' => old('entry_status_custom', $entryStatusCustom),
            'entry_due_date' => old('entry_due_date', optional($case?->entry_due_date)->format('Y-m-d')),
            'entry_amount' => old('entry_amount', $case?->entry_amount),
            'fees_amount' => old('fees_amount', $case?->fees_amount),
            'judicial_case_number' => old('judicial_case_number', $case?->judicial_case_number),
            'calc_base_date' => old('calc_base_date', optional($case?->calc_base_date)->format('Y-m-d') ?: now()->format('Y-m-d')),
            'owner_name' => $unit?->owner?->display_name,
            'owner_document' => $unit?->owner?->cpf_cnpj,
            'owner_email' => collect($unit?->owner?->emails_json ?? [])->first()['email'] ?? null,
            'owner_phone' => collect($unit?->owner?->phones_json ?? [])->first()['number'] ?? null,
            'tenant_name' => $unit?->tenant?->display_name,
        ];
    }

    private function repeaterDataFromCase(CobrancaCase $case): array
    {
        $emails = old('emails');
        $phones = old('phones');
        $quotas = old('quotas');
        $installments = old('installments');

        if (is_array($emails) || is_array($phones) || is_array($quotas) || is_array($installments)) {
            return [
                'emails' => $this->normalizeRows($emails, [['label' => 'Principal', 'value' => '']]),
                'phones' => $this->normalizeRows($phones, [['label' => 'Principal', 'value' => '', 'is_whatsapp' => 1]]),
                'quotas' => $this->normalizeRows($quotas, [['reference_label' => '', 'due_date' => '', 'original_amount' => '', 'updated_amount' => '', 'status' => 'taxa_mes', 'notes' => '']]),
                'installments' => $this->normalizeRows($installments, [['label' => '', 'installment_type' => 'parcela', 'installment_number' => '', 'due_date' => '', 'amount' => '', 'status' => 'pendente']]),
            ];
        }

        return [
            'emails' => $case->contacts->where('contact_type', 'email')->map(fn ($item) => [
                'label' => $item->label,
                'value' => $item->value,
            ])->values()->all() ?: [['label' => 'Principal', 'value' => '']],
            'phones' => $case->contacts->where('contact_type', 'phone')->map(fn ($item) => [
                'label' => $item->label,
                'value' => $item->value,
                'is_whatsapp' => $item->is_whatsapp ? 1 : 0,
            ])->values()->all() ?: [['label' => 'Principal', 'value' => '', 'is_whatsapp' => 1]],
            'quotas' => $case->quotas->map(fn ($item) => [
                'reference_label' => $item->reference_label,
                'due_date' => optional($item->due_date)->format('Y-m-d'),
                'original_amount' => $item->original_amount,
                'updated_amount' => $item->updated_amount,
                'status' => $this->normalizeQuotaKind($item->status),
                'notes' => $item->notes,
            ])->values()->all() ?: [['reference_label' => '', 'due_date' => '', 'original_amount' => '', 'updated_amount' => '', 'status' => 'taxa_mes', 'notes' => '']],
            'installments' => $case->installments->map(fn ($item) => [
                'label' => $item->label,
                'installment_type' => $item->installment_type,
                'installment_number' => $item->installment_number,
                'due_date' => optional($item->due_date)->format('Y-m-d'),
                'amount' => $item->amount,
                'status' => $item->status,
            ])->values()->all() ?: [['label' => '', 'installment_type' => 'parcela', 'installment_number' => '', 'due_date' => '', 'amount' => '', 'status' => 'pendente']],
        ];
    }

    private function defaultRepeaterData(): array
    {
        return [
            'emails' => $this->normalizeRows(old('emails'), [['label' => 'Principal', 'value' => '']]),
            'phones' => $this->normalizeRows(old('phones'), [['label' => 'Principal', 'value' => '', 'is_whatsapp' => 1]]),
            'quotas' => $this->normalizeRows(old('quotas'), [['reference_label' => '', 'due_date' => '', 'original_amount' => '', 'updated_amount' => '', 'status' => 'taxa_mes', 'notes' => '']]),
            'installments' => $this->normalizeRows(old('installments'), [['label' => '', 'installment_type' => 'parcela', 'installment_number' => '', 'due_date' => '', 'amount' => '', 'status' => 'pendente']]),
        ];
    }

    private function normalizeRows(mixed $rows, array $fallback): array
    {
        if (!is_array($rows)) {
            return $fallback;
        }

        $normalized = collect($rows)
            ->map(function ($row) {
                return is_array($row) ? $row : [];
            })
            ->filter(function (array $row) {
                return collect($row)->filter(fn ($value) => (string) $value !== '')->isNotEmpty();
            })
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $fallback;
    }

    private function emailRowsFromRequest(Request $request): array
    {
        return collect((array) $request->input('emails', []))
            ->map(function ($row, $index) {
                $value = strtolower(trim((string) ($row['value'] ?? '')));
                if ($value === '') {
                    return null;
                }
                return [
                    'label' => trim((string) ($row['label'] ?? '')) ?: ($index === 0 ? 'Principal' : ''),
                    'value' => Str::limit($value, 190, ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function phoneRowsFromRequest(Request $request): array
    {
        return collect((array) $request->input('phones', []))
            ->map(function ($row, $index) {
                $value = trim((string) ($row['value'] ?? ''));
                if ($value === '') {
                    return null;
                }
                return [
                    'label' => trim((string) ($row['label'] ?? '')) ?: ($index === 0 ? 'Principal' : ''),
                    'value' => Str::limit($value, 40, ''),
                    'is_whatsapp' => !empty($row['is_whatsapp']),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function quotaRowsFromRequest(Request $request): array
    {
        return collect((array) $request->input('quotas', []))
            ->map(function ($row) {
                $dueDate = trim((string) ($row['due_date'] ?? ''));
                $original = $this->moneyToDb($row['original_amount'] ?? null);
                $updated = $this->moneyToDb($row['updated_amount'] ?? null);
                $reference = $this->normalizeReferenceLabel((string) ($row['reference_label'] ?? ''));
                if ($dueDate === '' && $original === null && $updated === null && $reference === '') {
                    return null;
                }
                return [
                    'reference_label' => Str::limit($reference, 100, ''),
                    'due_date' => $dueDate ?: now()->toDateString(),
                    'original_amount' => $original ?? 0,
                    'updated_amount' => $updated,
                    'status' => $this->normalizeQuotaKind((string) ($row['status'] ?? 'taxa_mes')),
                    'notes' => Str::limit(trim((string) ($row['notes'] ?? '')), 190, ''),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function installmentRowsFromRequest(Request $request): array
    {
        return collect((array) $request->input('installments', []))
            ->map(function ($row) {
                $dueDate = trim((string) ($row['due_date'] ?? ''));
                $amount = $this->moneyToDb($row['amount'] ?? null);
                if ($dueDate === '' && ($amount === null || (float) $amount <= 0)) {
                    return null;
                }
                return [
                    'label' => Str::limit(trim((string) ($row['label'] ?? '')), 100, ''),
                    'installment_type' => trim((string) ($row['installment_type'] ?? 'parcela')) ?: 'parcela',
                    'installment_number' => (int) ($row['installment_number'] ?? 0),
                    'due_date' => $dueDate ?: null,
                    'amount' => $amount ?? 0,
                    'status' => trim((string) ($row['status'] ?? 'pendente')) ?: 'pendente',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function parseImportSpreadsheet(string $fullPath): array
    {
        $extension = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            return $this->parseImportXlsxNative($fullPath);
        }

        $script = base_path('scripts/parse_cobranca_import.py');
        if (!is_file($script)) {
            throw new \RuntimeException('Script de leitura de planilha não encontrado.');
        }

        $process = new Process(['python3', $script, $fullPath], timeout: 120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Falha desconhecida ao ler a planilha.');
        }

        $payload = json_decode($process->getOutput(), true);
        if (!is_array($payload) || !empty($payload['error'])) {
            throw new \RuntimeException((string) ($payload['error'] ?? 'A planilha retornou um formato inesperado.'));
        }

        return $payload;
    }

    private function parseImportXlsxNative(string $fullPath): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('A extensão ZIP do PHP não está disponível para ler arquivos .xlsx.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($fullPath) !== true) {
            throw new \RuntimeException('Não foi possível abrir o arquivo .xlsx enviado.');
        }

        try {
            $sharedStrings = $this->readXlsxSharedStrings($zip);
            $sheets = $this->readXlsxSheets($zip, $sharedStrings);
        } finally {
            $zip->close();
        }

        if ($sheets === []) {
            throw new \RuntimeException('A planilha não contém abas legíveis.');
        }

        $selected = $this->selectImportSheet($sheets);
        if (!$selected) {
            throw new \RuntimeException('A planilha não contém abas legíveis.');
        }

        $rows = $selected['rows'];
        $headerIndex = (int) $selected['header_index'];
        $headers = $selected['headers'];
        $score = (int) $selected['score'];

        if ($score < 4) {
            throw new \RuntimeException('Cabeçalhos obrigatórios não encontrados. Use: Condomínio, Bloco (opcional), Unidade, Referência, Vencimento e Valor.');
        }

        return [
            'sheet_name' => $selected['sheet_name'],
            'header_row_index' => $headerIndex + 1,
            'headers' => $headers,
            'rows' => array_slice($rows, $headerIndex + 1),
        ];
    }

    private function readXlsxSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || trim($xml) === '') {
            return [];
        }

        $root = @simplexml_load_string($xml);
        if (!$root) {
            return [];
        }

        $root->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $result = [];
        foreach ($root->xpath('//x:si') ?: [] as $item) {
            $this->registerSpreadsheetXmlNamespaces($item);
            $parts = [];
            foreach ($item->xpath('.//x:t') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }
            $result[] = implode('', $parts);
        }

        return $result;
    }

    private function readXlsxSheets(\ZipArchive $zip, array $sharedStrings): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            return [];
        }

        $workbook = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        if (!$workbook || !$rels) {
            return [];
        }

        $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $relationshipMap = [];
        foreach ($rels->xpath('//r:Relationship') ?: [] as $relationship) {
            $target = str_replace('\\', '/', (string) $relationship['Target']);
            if ($target !== '' && !str_starts_with($target, 'xl/')) {
                $target = ltrim($target, '/');
                if (!str_starts_with($target, 'xl/')) {
                    $target = 'xl/' . $target;
                }
            } else {
                $target = ltrim($target, '/');
            }
            $relationshipMap[(string) $relationship['Id']] = $target;
        }

        $sheets = [];
        foreach ($workbook->xpath('//x:sheets/x:sheet') ?: [] as $sheet) {
            $sheetName = (string) $sheet['name'];
            $relationshipId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = $relationshipMap[$relationshipId] ?? null;
            if (!$target) {
                continue;
            }
            $xml = $zip->getFromName($target);
            if ($xml === false) {
                continue;
            }
            $rows = $this->readXlsxSheetRows($xml, $sharedStrings);
            $sheets[] = ['sheet_name' => $sheetName, 'rows' => $rows];
        }

        return $sheets;
    }

    private function readXlsxSheetRows(string $xml, array $sharedStrings): array
    {
        $sheet = @simplexml_load_string($xml);
        if (!$sheet) {
            return [];
        }

        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $rowNode) {
            $this->registerSpreadsheetXmlNamespaces($rowNode);
            $current = [];
            foreach ($rowNode->xpath('./x:c') ?: [] as $cell) {
                $this->registerSpreadsheetXmlNamespaces($cell);
                $reference = (string) $cell['r'];
                $columnIndex = $this->xlsxColumnToIndex(preg_replace('/\d+/', '', $reference));
                $value = $this->xlsxCellValue($cell, $sharedStrings);
                $current[$columnIndex] = $value;
            }

            if ($current === []) {
                $rows[] = [];
                continue;
            }

            ksort($current);
            $maxIndex = max(array_keys($current));
            $normalized = [];
            for ($index = 0; $index <= $maxIndex; $index++) {
                $normalized[] = $current[$index] ?? '';
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    private function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $this->registerSpreadsheetXmlNamespaces($cell);

        $type = (string) ($cell['t'] ?? '');
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($cell->xpath('./x:is//x:t') ?: [] as $textNode) {
                $parts[] = (string) $textNode;
            }
            return trim(implode('', $parts));
        }

        $rawValue = (string) ($cell->v ?? '');
        if ($type === 's') {
            $sharedIndex = (int) $rawValue;
            return (string) ($sharedStrings[$sharedIndex] ?? '');
        }

        return trim($rawValue);
    }

    private function registerSpreadsheetXmlNamespaces(\SimpleXMLElement $node): void
    {
        $node->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $node->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $node->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
    }

    private function xlsxColumnToIndex(string $column): int
    {
        $column = strtoupper(trim($column));
        $index = 0;
        foreach (str_split($column) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }
        return max(0, $index - 1);
    }

    private function selectImportSheet(array $sheets): ?array
    {
        $best = null;
        $bestScore = -1;

        foreach ($sheets as $sheet) {
            [$headerIndex, $headerRow, $score] = $this->detectSpreadsheetHeaderRow($sheet['rows'] ?? []);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'sheet_name' => (string) ($sheet['sheet_name'] ?? 'Planilha 1'),
                    'rows' => $sheet['rows'] ?? [],
                    'header_index' => $headerIndex,
                    'headers' => $headerRow,
                    'score' => $score,
                ];
            }
        }

        return $best;
    }

    private function detectSpreadsheetHeaderRow(array $rows): array
    {
        $bestIndex = 0;
        $bestRow = $rows[0] ?? [];
        $bestScore = -1;

        foreach (array_slice($rows, 0, 20, true) as $index => $row) {
            $score = $this->scoreSpreadsheetHeaderRow((array) $row);
            if ($score > $bestScore) {
                $bestIndex = (int) $index;
                $bestRow = (array) $row;
                $bestScore = $score;
            }
            if ($score >= 5) {
                return [$bestIndex, $bestRow, $bestScore];
            }
        }

        return [$bestIndex, $bestRow, $bestScore];
    }

    private function scoreSpreadsheetHeaderRow(array $row): int
    {
        $found = [];
        foreach ($row as $cell) {
            $key = $this->normalizeLookupValue((string) $cell, false);
            $group = match ($key) {
                'CONDOMINIO', 'NOMECONDOMINIO' => 'condominium',
                'CNPJ', 'CNPJDOCONDOMINIO', 'CNPJCONDOMINIO' => 'condominium_cnpj',
                'BLOCO', 'TORRE' => 'block',
                'UNIDADE', 'UNID' => 'unit',
                'PROPRIETARIO', 'NOMEPROPRIETARIO', 'NOMEDOPROPRIETARIO' => 'owner',
                'REFERENCIA', 'COMPETENCIA', 'MESREF', 'MES' => 'reference',
                'VENCIMENTO', 'DATAVENCIMENTO' => 'due_date',
                'VALOR', 'TOTAL', 'VALORATUALIZADO', 'VALORORIGINAL' => 'amount',
                'TIPODACOTA', 'TIPOCOBRANCA', 'TIPOCOTA' => 'quota_type',
                default => null,
            };
            if ($group) {
                $found[$group] = true;
            }
        }
        return count($found);
    }

    private function mapSpreadsheetRowsToImport(array $headers, array $rows, int $headerRowIndex = 1): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = $this->normalizeLookupValue((string) $header, false);
            if ($key === '') {
                continue;
            }
            $map[$index] = match ($key) {
                'CONDOMINIO', 'NOMECONDOMINIO' => 'condominium',
                'CNPJ', 'CNPJDOCONDOMINIO', 'CNPJCONDOMINIO' => 'condominium_cnpj',
                'BLOCO', 'TORRE' => 'block',
                'UNIDADE', 'UNID' => 'unit',
                'PROPRIETARIO', 'NOMEPROPRIETARIO', 'NOMEDOPROPRIETARIO' => 'owner',
                'REFERENCIA', 'COMPETENCIA', 'MESREF', 'MES' => 'reference',
                'VENCIMENTO', 'DATAVENCIMENTO' => 'due_date',
                'VALOR', 'TOTAL', 'VALORATUALIZADO', 'VALORORIGINAL' => 'amount',
                'TIPODACOTA', 'TIPOCOBRANCA', 'TIPOCOTA' => 'quota_type',
                default => null,
            };
        }

        if (!in_array('condominium', $map, true) || !in_array('unit', $map, true) || !in_array('owner', $map, true) || !in_array('reference', $map, true) || !in_array('due_date', $map, true) || !in_array('amount', $map, true)) {
            throw new \RuntimeException('Cabecalhos obrigatorios nao encontrados. Use: Condominio, Proprietario, Unidade, Referencia, Vencimento e Valor. Bloco/Torre e Tipo da cota continuam opcionais.');
        }

        $result = [];
        foreach ($rows as $rowIndex => $row) {
            $payloadValues = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
            $entry = [
                'row_number' => $headerRowIndex + $rowIndex + 1,
                'raw_payload_json' => array_combine(array_map(fn ($item) => (string) $item, $headers), $payloadValues) ?: [],
                'condominium_input' => '',
                'block_input' => '',
                'unit_input' => '',
                'owner_input' => '',
                'reference_input' => '',
                'due_date_input' => '',
                'amount_value' => null,
                'quota_type_input' => '',
            ];

            foreach ($map as $index => $field) {
                if (!$field) {
                    continue;
                }
                $value = trim((string) ($row[$index] ?? ''));
                if ($field === 'amount') {
                    $entry['amount_value'] = $this->moneyToDb($value);
                } elseif ($field === 'due_date') {
                    $entry['due_date_input'] = $this->formatImportDateInput($value);
                } elseif ($field === 'reference') {
                    $entry['reference_input'] = $this->formatImportReferenceInput($value);
                } elseif ($field === 'quota_type') {
                    $entry['quota_type_input'] = trim($value);
                } else {
                    $entry[$field . '_input'] = $value;
                }
            }

            if (
                $entry['condominium_input'] === '' &&
                $entry['unit_input'] === '' &&
                $entry['owner_input'] === '' &&
                $entry['reference_input'] === '' &&
                $entry['due_date_input'] === '' &&
                $entry['amount_value'] === null
            ) {
                continue;
            }

            $result[] = $entry;
        }

        return $result;
    }

    private function classifyImportBatch(CobrancaImportBatch $batch): void
    {
        $this->classifyImportBatchV2($batch);
        return;

        $batch->loadMissing('rows');

        $condominiums = ClientCondominium::query()->get(['id', 'name', 'has_blocks']);
        $blocks = ClientBlock::query()->get(['id', 'condominium_id', 'name']);
        $units = ClientUnit::query()->with(['condominium', 'block'])->get(['id', 'condominium_id', 'block_id', 'unit_number', 'owner_entity_id']);

        $condoLookup = [];
        foreach ($condominiums as $condo) {
            $condoLookup[$this->normalizeLookupValue((string) $condo->name)][] = $condo;
        }

        $blockLookup = [];
        foreach ($blocks as $block) {
            $blockLookup[(int) $block->condominium_id][$this->normalizeLookupValue((string) $block->name)][] = $block;
        }

        $unitLookup = [];
        $unitLookupWithoutBlock = [];
        foreach ($units as $unit) {
            $condoId = (int) $unit->condominium_id;
            $blockKey = $unit->block_id ? $this->normalizeLookupValue((string) optional($unit->block)->name) : '';
            $unitKey = $this->normalizeLookupValue((string) $unit->unit_number);
            $unitLookup[$condoId][$blockKey][$unitKey][] = $unit;
            $unitLookupWithoutBlock[$condoId][$unitKey][] = $unit;
        }

        $summary = [
            'total_rows' => 0,
            'ready_rows' => 0,
            'pending_rows' => 0,
        ];

        foreach ($batch->rows as $row) {
            $summary['total_rows']++;
            $condoName = $this->normalizeLookupValue((string) $row->condominium_input);
            $blockName = $this->normalizeBlockInput((string) $row->block_input);
            $unitName = $this->normalizeLookupValue((string) $row->unit_input);
            $referenceInput = $this->formatImportReferenceInput((string) $row->reference_input);
            $dueDateInput = $this->formatImportDateInput((string) $row->due_date_input);
            $reference = $this->normalizeReferenceLabel($referenceInput);
            $dueDate = $this->normalizeImportDate($dueDateInput);
            $amount = $row->amount_value !== null ? (float) $row->amount_value : null;

            $message = null;
            $status = 'ready';
            $matchedUnit = null;

            $condoCandidates = $condoLookup[$condoName] ?? [];
            if ($condoName === '' || $condoCandidates === []) {
                $status = 'pending';
                $message = 'Condomínio não encontrado na base de clientes.';
            } elseif ($unitName === '') {
                $status = 'pending';
                $message = 'Unidade não informada.';
            } else {
                $condo = $condoCandidates[0];
                $condoId = (int) $condo->id;
                if ($condo->has_blocks) {
                    if ($blockName === '') {
                        $candidates = $unitLookupWithoutBlock[$condoId][$unitName] ?? [];
                        if (count($candidates) === 1) {
                            $matchedUnit = $candidates[0];
                        } elseif (count($candidates) > 1) {
                            $status = 'pending';
                            $message = 'Condomínio possui blocos. Informe o bloco para identificar a unidade.';
                        } else {
                            $status = 'pending';
                            $message = 'Unidade não encontrada para este condomínio.';
                        }
                    } else {
                        $blockCandidates = $blockLookup[$condoId][$blockName] ?? [];
                        if ($blockCandidates === []) {
                            $status = 'pending';
                            $message = 'Bloco não encontrado no condomínio informado.';
                        } else {
                            $matchedUnits = $unitLookup[$condoId][$blockName][$unitName] ?? [];
                            if (count($matchedUnits) === 1) {
                                $matchedUnit = $matchedUnits[0];
                            } elseif (count($matchedUnits) > 1) {
                                $status = 'pending';
                                $message = 'Mais de uma unidade encontrada com os mesmos dados.';
                            } else {
                                $status = 'pending';
                                $message = 'Unidade não encontrada para o bloco informado.';
                            }
                        }
                    }
                } else {
                    $matchedUnits = $unitLookupWithoutBlock[$condoId][$unitName] ?? [];
                    if (count($matchedUnits) === 1) {
                        $matchedUnit = $matchedUnits[0];
                    } elseif (count($matchedUnits) > 1) {
                        $status = 'pending';
                        $message = 'Mais de uma unidade encontrada com os mesmos dados.';
                    } else {
                        $status = 'pending';
                        $message = 'Unidade não encontrada para este condomínio.';
                    }
                }
            }

            if ($status === 'ready' && !$this->isValidReferenceLabel($reference)) {
                $status = 'pending';
                $message = 'Referência inválida. Use mm/aaaa.';
            }
            if ($status === 'ready' && $dueDate === null) {
                $status = 'pending';
                $message = 'Vencimento inválido. Use dd/mm/aaaa.';
            }
            if ($status === 'ready' && $amount === null) {
                $status = 'pending';
                $message = 'Valor inválido.';
            }
            if ($status === 'ready' && !$matchedUnit?->owner_entity_id) {
                $status = 'pending';
                $message = 'A unidade não possui proprietário vinculado no cadastro.';
            }

            $row->update([
                'matched_unit_id' => $matchedUnit?->id,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'status' => $status,
                'message' => $message ?: 'Linha pronta para processamento.',
            ]);

            if ($status === 'ready') {
                $summary['ready_rows']++;
            } else {
                $summary['pending_rows']++;
            }
        }

        $batch->update([
            'total_rows' => $summary['total_rows'],
            'ready_rows' => $summary['ready_rows'],
            'pending_rows' => $summary['pending_rows'],
            'summary_json' => $summary,
        ]);
    }

    private function classifyImportBatchV2(CobrancaImportBatch $batch): void
    {
        $batch->loadMissing('rows');
        $context = $this->buildImportContext();

        foreach ($batch->rows as $row) {
            $this->classifyImportRow($row, $context);
        }

        $this->recalculateImportBatchSummary($batch->fresh('rows'));
    }

    private function emptyImportSummary(): array
    {
        return [
            'total_rows' => 0,
            'ready_rows' => 0,
            'blocking_rows' => 0,
            'ignored_rows' => 0,
            'processed_rows' => 0,
            'counts' => array_fill_keys(array_keys($this->importStatusLabels()), 0),
            'processed' => [],
        ];
    }

    private function importStatusLabels(): array
    {
        return [
            'ready_create' => 'Valido · nova OS',
            'ready_link' => 'Valido · OS existente',
            'warning_duplicate' => 'Duplicidade',
            'warning_multi_case' => 'Conflito de OS',
            'error_condominium' => 'Erro condominio',
            'error_unit' => 'Erro unidade',
            'error_required' => 'Erro obrigatorio',
            'ignored_owner' => 'Ignorado · proprietario',
            'ignored_manual' => 'Ignorado manualmente',
            'processed_created' => 'Processado · OS criada',
            'processed_linked' => 'Processado · OS existente',
            'processed_duplicate_skip' => 'Nao importado · duplicado',
            'processed_error' => 'Erro no processamento',
        ];
    }

    private function importStatusStyles(): array
    {
        return [
            'ready_create' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
            'ready_link' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
            'warning_duplicate' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
            'warning_multi_case' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
            'error_condominium' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
            'error_unit' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
            'error_required' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
            'ignored_owner' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            'ignored_manual' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            'processed_created' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
            'processed_linked' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300',
            'processed_duplicate_skip' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            'processed_error' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300',
        ];
    }

    private function importBlockingStatuses(): array
    {
        return ['warning_duplicate', 'warning_multi_case', 'error_condominium', 'error_unit', 'error_required'];
    }

    private function importBatchSummary(CobrancaImportBatch $batch): array
    {
        return array_replace_recursive($this->emptyImportSummary(), $batch->summary_json ?? []);
    }

    private function recalculateImportBatchSummary(CobrancaImportBatch $batch, array $updates = []): void
    {
        $rows = $batch->relationLoaded('rows') ? $batch->rows : $batch->rows()->get();
        $counts = array_fill_keys(array_keys($this->importStatusLabels()), 0);
        $summaryOverrides = $updates['summary_json'] ?? [];
        $persistedUpdates = $updates;
        unset($persistedUpdates['summary_json']);

        foreach ($rows as $row) {
            if (array_key_exists((string) $row->status, $counts)) {
                $counts[$row->status]++;
            }
        }

        $readyRows = $counts['ready_create'] + $counts['ready_link'];
        $blockingRows = $counts['warning_duplicate'] + $counts['warning_multi_case'] + $counts['error_condominium'] + $counts['error_unit'] + $counts['error_required'];
        $ignoredRows = $counts['ignored_owner'] + $counts['ignored_manual'] + $counts['processed_duplicate_skip'];
        $processedRows = $counts['processed_created'] + $counts['processed_linked'] + $counts['processed_duplicate_skip'] + $counts['processed_error'];

        $summary = array_replace_recursive(
            $this->emptyImportSummary(),
            $batch->summary_json ?? [],
            $summaryOverrides,
            [
                'total_rows' => $rows->count(),
                'ready_rows' => $readyRows,
                'blocking_rows' => $blockingRows,
                'ignored_rows' => $ignoredRows,
                'processed_rows' => $processedRows,
                'counts' => $counts,
            ]
        );

        $batch->update(array_merge([
            'total_rows' => $rows->count(),
            'ready_rows' => $readyRows,
            'pending_rows' => $blockingRows,
            'duplicate_rows' => $counts['warning_duplicate'] + $counts['processed_duplicate_skip'],
            'summary_json' => $summary,
        ], $persistedUpdates));
    }

    private function buildImportContext(): array
    {
        $condominiums = ClientCondominium::query()->orderBy('name')->get(['id', 'name', 'cnpj', 'has_blocks']);
        $blocks = ClientBlock::query()->orderBy('condominium_id')->orderBy('name')->get(['id', 'condominium_id', 'name']);
        $units = ClientUnit::query()
            ->with(['condominium', 'block', 'owner'])
            ->orderBy('condominium_id')
            ->orderBy('unit_number')
            ->get(['id', 'condominium_id', 'block_id', 'unit_number', 'owner_entity_id']);

        $condominiumsByName = [];
        $condominiumsByCnpj = [];
        $condominiumList = [];
        foreach ($condominiums as $condominium) {
            $normalized = $this->normalizeLookupValue((string) $condominium->name);
            $condominiumsByName[$normalized][] = $condominium;
            $digits = $this->digitsOnly((string) $condominium->cnpj);
            if ($digits !== '') {
                $condominiumsByCnpj[$digits] = $condominium;
            }
            $condominiumList[] = [
                'id' => (int) $condominium->id,
                'name' => (string) $condominium->name,
                'normalized' => $normalized,
                'cnpj' => $digits,
                'has_blocks' => (bool) $condominium->has_blocks,
            ];
        }

        $blocksByCondominium = [];
        $blockListByCondominium = [];
        foreach ($blocks as $block) {
            $normalized = $this->normalizeLookupValue((string) $block->name);
            $blocksByCondominium[(int) $block->condominium_id][$normalized][] = $block;
            $blockListByCondominium[(int) $block->condominium_id][] = $block;
        }

        $unitsByCondominiumBlock = [];
        $unitsByCondominium = [];
        $unitListByCondominium = [];
        foreach ($units as $unit) {
            $condoId = (int) $unit->condominium_id;
            $blockKey = $unit->block_id ? $this->normalizeLookupValue((string) optional($unit->block)->name) : '';
            $unitKey = $this->normalizeLookupValue((string) $unit->unit_number);
            $unitsByCondominiumBlock[$condoId][$blockKey][$unitKey][] = $unit;
            $unitsByCondominium[$condoId][$unitKey][] = $unit;
            $unitListByCondominium[$condoId][] = $unit;
        }

        return [
            'condominiums_by_name' => $condominiumsByName,
            'condominiums_by_cnpj' => $condominiumsByCnpj,
            'condominium_list' => $condominiumList,
            'blocks_by_condominium' => $blocksByCondominium,
            'block_list_by_condominium' => $blockListByCondominium,
            'units_by_condominium_block' => $unitsByCondominiumBlock,
            'units_by_condominium' => $unitsByCondominium,
            'unit_list_by_condominium' => $unitListByCondominium,
        ];
    }

    private function classifyImportRow(CobrancaImportRow $row, array $context): void
    {
        $referenceInput = $this->formatImportReferenceInput((string) $row->reference_input);
        $dueDateInput = $this->formatImportDateInput((string) $row->due_date_input);
        $reference = $this->normalizeReferenceLabel($referenceInput);
        $dueDate = $this->normalizeImportDate($dueDateInput);
        $amount = $row->amount_value !== null ? (float) $row->amount_value : null;
        $resolutionPayload = is_array($row->resolution_payload_json) ? $row->resolution_payload_json : [];
        $decision = (string) ($resolutionPayload['decision'] ?? '');
        $quotaType = $this->normalizeQuotaKind($row->quota_type_input);

        if ($decision === 'ignore_line') {
            $row->update([
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => 'manual_ignore',
                'issue_payload_json' => null,
                'matched_unit_id' => null,
                'matched_case_id' => null,
                'status' => 'ignored_manual',
                'message' => 'Linha ignorada manualmente antes do processamento.',
            ]);
            return;
        }

        $missingFields = [];
        if (trim((string) $row->condominium_input) === '') $missingFields[] = 'condominio';
        if (trim((string) $row->unit_input) === '') $missingFields[] = 'unidade';
        if (trim((string) $row->owner_input) === '') $missingFields[] = 'proprietario';
        if ($reference === '' || !$this->isValidReferenceLabel($reference)) $missingFields[] = 'referencia';
        if ($dueDate === null) $missingFields[] = 'vencimento';
        if ($amount === null) $missingFields[] = 'valor';

        if ($missingFields !== []) {
            $row->update([
                'matched_unit_id' => null,
                'matched_case_id' => null,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => 'required_fields_missing',
                'issue_payload_json' => ['missing_fields' => $missingFields],
                'status' => 'error_required',
                'message' => 'Campos obrigatorios ausentes ou invalidos: ' . implode(', ', $missingFields) . '.',
            ]);
            return;
        }

        $condominiumMatch = $this->matchImportCondominium($row, $context);
        if (!$condominiumMatch['condominium']) {
            $row->update([
                'matched_unit_id' => null,
                'matched_case_id' => null,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => $condominiumMatch['issue_code'],
                'issue_payload_json' => $condominiumMatch['issue_payload'],
                'status' => 'error_condominium',
                'message' => $condominiumMatch['message'],
            ]);
            return;
        }

        /** @var ClientCondominium $condominium */
        $condominium = $condominiumMatch['condominium'];
        $unitMatch = $this->matchImportUnit($condominium, $row, $context);
        if (!$unitMatch['unit']) {
            $row->update([
                'matched_unit_id' => null,
                'matched_case_id' => null,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => $unitMatch['issue_code'],
                'issue_payload_json' => $unitMatch['issue_payload'],
                'status' => 'error_unit',
                'message' => $unitMatch['message'],
            ]);
            return;
        }

        /** @var ClientUnit $unit */
        $unit = $unitMatch['unit'];
        $ownerName = (string) ($unit->owner?->display_name ?? '');
        if ($ownerName === '') {
            $row->update([
                'matched_unit_id' => $unit->id,
                'matched_case_id' => null,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => 'unit_without_owner',
                'issue_payload_json' => null,
                'status' => 'error_required',
                'message' => 'A unidade encontrada nao possui proprietario ativo vinculado no cadastro.',
            ]);
            return;
        }

        if (!$this->isOwnerNameCompatible((string) $row->owner_input, $ownerName)) {
            $row->update([
                'matched_unit_id' => $unit->id,
                'matched_case_id' => null,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => 'owner_mismatch',
                'issue_payload_json' => [
                    'owner_from_sheet' => (string) $row->owner_input,
                    'owner_from_system' => $ownerName,
                    'recommendation' => 'Atualize o cadastro da unidade no sistema e realize nova importacao.',
                ],
                'status' => 'ignored_owner',
                'message' => 'Nome do proprietario divergente. Atualize o cadastro da unidade no sistema e realize nova importacao.',
            ]);
            return;
        }

        $duplicateCases = $this->findDuplicateCasesForQuota($unit->id, $reference, $dueDate, $amount, $quotaType);
        if ($duplicateCases !== []) {
            $caseOptions = array_map(fn (CobrancaCase $case) => $this->formatCaseForImportPreview($case), $duplicateCases);

            if ($decision === 'create_new_case') {
                $row->update([
                    'matched_unit_id' => $unit->id,
                    'matched_case_id' => null,
                    'reference_input' => $referenceInput,
                    'due_date_input' => $dueDateInput,
                    'issue_code' => 'duplicate_existing_quota',
                    'issue_payload_json' => ['case_options' => $caseOptions],
                    'status' => 'ready_create',
                    'message' => 'Duplicidade confirmada manualmente. O sistema abrira uma nova OS para esta cota.',
                ]);
                return;
            }

            $selectedCase = $duplicateCases[0];
            $row->update([
                'matched_unit_id' => $unit->id,
                'matched_case_id' => $selectedCase->id,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => 'duplicate_existing_quota',
                'issue_payload_json' => ['case_options' => $caseOptions],
                'status' => 'warning_duplicate',
                'message' => 'Ja existe uma OS aberta para esta mesma cota. Revise e decida se deseja criar outra OS ou ignorar a linha.',
            ]);
            return;
        }

        $reusableCases = $this->findReusableCasesForUnit($unit->id);
        if (count($reusableCases) === 1) {
            $case = $reusableCases[0];
            $summary = $this->formatCaseForImportPreview($case);
            $row->update([
                'matched_unit_id' => $unit->id,
                'matched_case_id' => $case->id,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => 'reuse_existing_case',
                'issue_payload_json' => ['case_options' => [$summary]],
                'status' => 'ready_link',
                'message' => 'Esta linha sera vinculada automaticamente a OS ' . $case->os_number . '. Cotas atuais: ' . $summary['quotas_count'] . ' · nova quantidade: ' . ($summary['quotas_count'] + 1) . '.',
            ]);
            return;
        }

        if (count($reusableCases) > 1) {
            $caseOptions = array_map(fn (CobrancaCase $case) => $this->formatCaseForImportPreview($case), $reusableCases);
            $selectedCaseId = (int) ($resolutionPayload['target_case_id'] ?? 0);

            if ($decision === 'use_case' && $selectedCaseId > 0) {
                foreach ($reusableCases as $candidate) {
                    if ((int) $candidate->id === $selectedCaseId) {
                        $row->update([
                            'matched_unit_id' => $unit->id,
                            'matched_case_id' => $candidate->id,
                            'reference_input' => $referenceInput,
                            'due_date_input' => $dueDateInput,
                            'issue_code' => 'multiple_reusable_cases',
                            'issue_payload_json' => ['case_options' => $caseOptions],
                            'status' => 'ready_link',
                            'message' => 'Linha configurada para aproveitar manualmente a OS ' . $candidate->os_number . '.',
                        ]);
                        return;
                    }
                }
            }

            if ($decision === 'create_new_case') {
                $row->update([
                    'matched_unit_id' => $unit->id,
                    'matched_case_id' => null,
                    'reference_input' => $referenceInput,
                    'due_date_input' => $dueDateInput,
                    'issue_code' => 'multiple_reusable_cases',
                    'issue_payload_json' => ['case_options' => $caseOptions],
                    'status' => 'ready_create',
                    'message' => 'Conflito de OS resolvido manualmente. Uma nova OS sera criada para esta linha.',
                ]);
                return;
            }

            $row->update([
                'matched_unit_id' => $unit->id,
                'matched_case_id' => null,
                'reference_input' => $referenceInput,
                'due_date_input' => $dueDateInput,
                'issue_code' => 'multiple_reusable_cases',
                'issue_payload_json' => ['case_options' => $caseOptions],
                'status' => 'warning_multi_case',
                'message' => 'Foi encontrada mais de uma OS aberta apta a receber esta cota. Escolha qual OS deve ser usada, crie uma nova ou ignore a linha.',
            ]);
            return;
        }

        $row->update([
            'matched_unit_id' => $unit->id,
            'matched_case_id' => null,
            'reference_input' => $referenceInput,
            'due_date_input' => $dueDateInput,
            'issue_code' => 'create_new_case',
            'issue_payload_json' => null,
            'status' => 'ready_create',
            'message' => 'Linha valida. Nao existe OS aberta reaproveitavel para esta unidade, entao uma nova OS sera criada.',
        ]);
    }

    private function normalizeLookupValue(string $value, bool $emptyNumericZero = true): string
    {
        $value = trim(mb_strtoupper($value, 'UTF-8'));
        $value = strtr($value, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ]);
        $value = preg_replace('/[^A-Z0-9]+/u', '', $value) ?: '';
        if ($emptyNumericZero && in_array($value, ['', '0', '-'], true)) {
            return '';
        }
        return $value;
    }

    private function normalizeBlockInput(string $value): string
    {
        $normalized = $this->normalizeLookupValue($value);
        if (in_array($normalized, ['', '0', 'SEM', 'SEMBLOCO', 'NAO', 'NAOBLOCO'], true)) {
            return '';
        }
        return $normalized;
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function normalizeComparableText(string $value): string
    {
        $normalized = $this->normalizeLookupValue($value, false);
        foreach ([' DOS ', ' DAS ', ' DO ', ' DA ', ' DE ', ' E '] as $token) {
            $normalized = str_replace($this->normalizeLookupValue($token, false), '', $normalized);
        }
        return $normalized;
    }

    private function isOwnerNameCompatible(string $sheetName, string $systemName): bool
    {
        return $this->normalizeComparableText($sheetName) !== ''
            && $this->normalizeComparableText($sheetName) === $this->normalizeComparableText($systemName);
    }

    private function extractImportCondominiumCnpj(CobrancaImportRow $row): string
    {
        $payload = is_array($row->raw_payload_json) ? $row->raw_payload_json : [];
        foreach ($payload as $header => $value) {
            $normalized = $this->normalizeLookupValue((string) $header, false);
            if (in_array($normalized, ['CNPJ', 'CNPJDOCONDOMINIO', 'CNPJCONDOMINIO'], true)) {
                return $this->digitsOnly((string) $value);
            }
        }
        return '';
    }

    private function similarityScore(string $left, string $right): int
    {
        if ($left === '' || $right === '') {
            return 0;
        }

        similar_text($left, $right, $percent);
        if (str_contains($left, $right) || str_contains($right, $left)) {
            $percent = max($percent, 88.0);
        }

        return (int) round($percent);
    }

    private function importUnitHints(string $blockInput, string $unitInput): array
    {
        $rawBlock = trim($blockInput);
        $rawUnit = trim($unitInput);
        $blockKey = $this->normalizeBlockInput($rawBlock);
        $unitKey = $this->normalizeLookupValue($rawUnit);
        $unitDigits = $this->digitsOnly($rawUnit);
        $blockTokens = [];
        $unitCandidates = array_values(array_filter(array_unique([$unitKey, $unitDigits])));

        if ($blockKey !== '') {
            $blockTokens[] = $blockKey;
            $blockDigits = $this->digitsOnly($rawBlock);
            if ($blockDigits !== '') {
                $blockTokens[] = $blockDigits;
            }
        }

        if ($rawUnit !== '') {
            $normalizedRawUnit = $this->normalizeLookupValue($rawUnit);

            if (preg_match('/^(?:BLOCO|TORRE)?([A-Z]{1,4})[-\s\/]*(\d{1,6})$/i', $normalizedRawUnit, $matches)) {
                $embeddedBlock = $this->normalizeBlockInput($matches[1]);
                $embeddedUnit = $this->normalizeLookupValue($matches[2]);
                if ($embeddedBlock !== '') {
                    $blockTokens[] = $embeddedBlock;
                }
                if ($embeddedUnit !== '') {
                    $unitCandidates[] = $embeddedUnit;
                }
            }

            if (preg_match('/^(?:BLOCO|TORRE)([A-Z0-9]{1,6})(\d{1,6})$/i', $normalizedRawUnit, $matches)) {
                $embeddedBlock = $this->normalizeBlockInput($matches[1]);
                $embeddedUnit = $this->normalizeLookupValue($matches[2]);
                if ($embeddedBlock !== '') {
                    $blockTokens[] = $embeddedBlock;
                }
                if ($embeddedUnit !== '') {
                    $unitCandidates[] = $embeddedUnit;
                }
            }
        }

        return [
            'raw_block' => $rawBlock,
            'raw_unit' => $rawUnit,
            'block_key' => $blockKey,
            'block_tokens' => array_values(array_unique(array_filter($blockTokens))),
            'unit_key' => $unitKey,
            'unit_digits' => $unitDigits,
            'unit_candidates' => array_values(array_unique(array_filter($unitCandidates))),
        ];
    }

    private function importUnitCandidateScore(ClientUnit $unit, array $hints): int
    {
        $unitKey = $this->normalizeLookupValue((string) $unit->unit_number);
        $unitDigits = $this->digitsOnly((string) $unit->unit_number);
        $blockName = (string) ($unit->block?->name ?? '');
        $blockKey = $this->normalizeBlockInput($blockName);
        $score = 0;

        foreach ($hints['unit_candidates'] ?? [] as $candidate) {
            if ($candidate === $unitKey) {
                $score = max($score, 100);
            }

            if ($candidate !== '' && $unitDigits !== '' && $candidate === $unitDigits) {
                $score = max($score, 96);
            }

            if ($candidate !== '' && ($this->similarityScore($candidate, $unitKey) >= 80 || ($unitDigits !== '' && $this->similarityScore($candidate, $unitDigits) >= 85))) {
                $score = max($score, 88);
            }
        }

        $rawUnit = (string) ($hints['raw_unit'] ?? '');
        if ($rawUnit !== '') {
            $label = trim(($blockName !== '' ? $blockName . ' - ' : '') . (string) $unit->unit_number);
            $score = max(
                $score,
                (int) floor($this->similarityScore($this->normalizeLookupValue($rawUnit), $this->normalizeLookupValue($label)) * 0.8)
            );
        }

        if ($blockKey !== '') {
            $providedBlockKey = (string) ($hints['block_key'] ?? '');
            $blockTokens = (array) ($hints['block_tokens'] ?? []);

            if ($providedBlockKey !== '' && $providedBlockKey === $blockKey) {
                $score += 10;
            }

            foreach ($blockTokens as $token) {
                if ($token === '') {
                    continue;
                }

                if ($token === $blockKey || str_contains($blockKey, $token) || str_contains($token, $blockKey)) {
                    $score += 8;
                    break;
                }
            }
        } elseif (($hints['block_key'] ?? '') === '') {
            $score += 4;
        }

        return min(110, $score);
    }

    private function findSmartUnitCandidates(int $condominiumId, array $context, array $hints): array
    {
        return collect($context['unit_list_by_condominium'][$condominiumId] ?? [])
            ->map(function (ClientUnit $unit) use ($hints) {
                $blockName = (string) ($unit->block?->name ?? '');
                $label = trim(($blockName !== '' ? $blockName . ' - ' : '') . ((string) $unit->unit_number));
                $score = $this->importUnitCandidateScore($unit, $hints);

                return [
                    'unit' => $unit,
                    'id' => (int) $unit->id,
                    'condominium_name' => (string) ($unit->condominium?->name ?? ''),
                    'block_name' => $blockName,
                    'unit_number' => (string) $unit->unit_number,
                    'label' => $label,
                    'owner_name' => (string) ($unit->owner?->display_name ?? ''),
                    'score' => $score,
                ];
            })
            ->filter(fn (array $item) => (int) $item['score'] >= 35)
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    private function matchImportCondominium(CobrancaImportRow $row, array $context): array
    {
        $inputName = trim((string) $row->condominium_input);
        $normalizedName = $this->normalizeLookupValue($inputName);
        $cnpj = $this->extractImportCondominiumCnpj($row);

        if ($cnpj !== '' && isset($context['condominiums_by_cnpj'][$cnpj])) {
            return [
                'condominium' => $context['condominiums_by_cnpj'][$cnpj],
                'issue_code' => null,
                'issue_payload' => null,
                'message' => null,
            ];
        }

        $exactCandidates = $context['condominiums_by_name'][$normalizedName] ?? [];
        if (count($exactCandidates) === 1) {
            return [
                'condominium' => $exactCandidates[0],
                'issue_code' => null,
                'issue_payload' => null,
                'message' => null,
            ];
        }

        $suggestions = collect($context['condominium_list'])
            ->map(function (array $candidate) use ($normalizedName, $cnpj) {
                $score = $this->similarityScore($normalizedName, (string) $candidate['normalized']);
                if ($cnpj !== '' && $cnpj === (string) $candidate['cnpj']) {
                    $score = 100;
                }

                return array_merge($candidate, ['score' => $score]);
            })
            ->sortByDesc('score')
            ->take(5)
            ->filter(fn (array $item) => (int) $item['score'] >= 40)
            ->values()
            ->map(fn (array $item) => [
                'id' => $item['id'],
                'name' => $item['name'],
                'cnpj' => $item['cnpj'],
                'score' => $item['score'],
            ])
            ->all();

        return [
            'condominium' => null,
            'issue_code' => 'condominium_not_found',
            'issue_payload' => [
                'received_name' => $inputName,
                'received_cnpj' => $cnpj,
                'suggestions' => $suggestions,
            ],
            'message' => $suggestions === []
                ? 'Condominio nao encontrado exatamente no cadastro.'
                : 'Condominio nao encontrado exatamente no cadastro. Revise as sugestoes antes de continuar.',
        ];
    }

    private function matchImportUnit(ClientCondominium $condominium, CobrancaImportRow $row, array $context): array
    {
        $condominiumId = (int) $condominium->id;
        $hints = $this->importUnitHints((string) $row->block_input, (string) $row->unit_input);
        $unitKey = (string) ($hints['unit_key'] ?? '');
        $blockKey = (string) ($hints['block_key'] ?? '');
        $smartCandidates = $this->findSmartUnitCandidates($condominiumId, $context, $hints);
        $bestSmartCandidate = $smartCandidates[0] ?? null;
        $secondSmartCandidate = $smartCandidates[1] ?? null;
        $canAutoMatchSmartCandidate = $bestSmartCandidate
            && ($bestSmartCandidate['score'] ?? 0) >= 94
            && (($secondSmartCandidate['score'] ?? 0) <= (($bestSmartCandidate['score'] ?? 0) - 8));

        if ($unitKey === '') {
            return [
                'unit' => null,
                'issue_code' => 'unit_not_found',
                'issue_payload' => null,
                'message' => 'Unidade nao informada.',
            ];
        }

        $suggestions = function () use ($smartCandidates): array {
            return collect($smartCandidates)
                ->take(6)
                ->map(fn (array $item) => Arr::except($item, ['unit']))
                ->values()
                ->all();
        };

        if ((bool) $condominium->has_blocks) {
            if ($blockKey === '') {
                $candidates = $context['units_by_condominium'][$condominiumId][$unitKey] ?? [];
                if (count($candidates) === 1) {
                    return [
                        'unit' => $candidates[0],
                        'issue_code' => null,
                        'issue_payload' => null,
                        'message' => null,
                    ];
                }

                if ($canAutoMatchSmartCandidate) {
                    return [
                        'unit' => $bestSmartCandidate['unit'],
                        'issue_code' => null,
                        'issue_payload' => null,
                        'message' => null,
                    ];
                }

                return [
                    'unit' => null,
                    'issue_code' => 'unit_not_found',
                    'issue_payload' => [
                        'received_block' => (string) $row->block_input,
                        'received_unit' => (string) $row->unit_input,
                        'suggestions' => $suggestions(),
                    ],
                    'message' => count($candidates) > 1
                        ? 'Condominio possui mais de uma unidade com essa numeracao em blocos diferentes. Informe ou corrija o bloco.'
                        : 'Unidade nao encontrada para este condominio.',
                ];
            }

            $blockCandidates = $context['blocks_by_condominium'][$condominiumId][$blockKey] ?? [];
            if ($blockCandidates === []) {
                return [
                    'unit' => null,
                    'issue_code' => 'block_not_found',
                    'issue_payload' => [
                        'received_block' => (string) $row->block_input,
                        'received_unit' => (string) $row->unit_input,
                        'suggestions' => $suggestions(),
                    ],
                    'message' => 'Bloco/Torre nao encontrado no condominio informado.',
                ];
            }

            $candidates = $context['units_by_condominium_block'][$condominiumId][$blockKey][$unitKey] ?? [];
            if (count($candidates) === 1) {
                return [
                    'unit' => $candidates[0],
                    'issue_code' => null,
                    'issue_payload' => null,
                    'message' => null,
                ];
            }

            if ($canAutoMatchSmartCandidate) {
                return [
                    'unit' => $bestSmartCandidate['unit'],
                    'issue_code' => null,
                    'issue_payload' => null,
                    'message' => null,
                ];
            }

            return [
                'unit' => null,
                'issue_code' => 'unit_not_found',
                'issue_payload' => [
                    'received_block' => (string) $row->block_input,
                    'received_unit' => (string) $row->unit_input,
                    'suggestions' => $suggestions(),
                ],
                'message' => 'Unidade nao encontrada para o bloco informado.',
            ];
        }

        $candidates = $context['units_by_condominium'][$condominiumId][$unitKey] ?? [];
        if (count($candidates) === 1) {
            return [
                'unit' => $candidates[0],
                'issue_code' => null,
                'issue_payload' => null,
                'message' => null,
            ];
        }

        if ($canAutoMatchSmartCandidate) {
            return [
                'unit' => $bestSmartCandidate['unit'],
                'issue_code' => null,
                'issue_payload' => null,
                'message' => null,
            ];
        }

        return [
            'unit' => null,
            'issue_code' => 'unit_not_found',
            'issue_payload' => [
                'received_block' => (string) $row->block_input,
                'received_unit' => (string) $row->unit_input,
                'suggestions' => $suggestions(),
            ],
            'message' => count($candidates) > 1
                ? 'Mais de uma unidade encontrada com os mesmos dados.'
                : 'Unidade nao encontrada para este condominio.',
        ];
    }

    private function findReusableCasesForUnit(int $unitId): array
    {
        return CobrancaCase::query()
            ->with(['quotas', 'creator'])
            ->where('unit_id', $unitId)
            ->orderByDesc('last_progress_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (CobrancaCase $case) => $this->isCaseReusableForImport($case))
            ->values()
            ->all();
    }

    private function isCaseReusableForImport(CobrancaCase $case): bool
    {
        $situation = (string) $case->situation;
        $workflowStage = $this->normalizeWorkflowStage((string) $case->workflow_stage);
        $billingStatus = (string) $case->billing_status;

        if (in_array($situation, ['cancelado', 'pago_encerrado', 'ajuizado', 'em_pagamento_acordo', 'acordo_nao_pago', 'acordo_renegociado'], true)) {
            return false;
        }

        if (in_array($workflowStage, ['aguardando_assinatura', 'acordo_ativo', 'acordo_inadimplido', 'judicializado', 'pago_encerrado', 'cancelado'], true)) {
            return false;
        }

        if ($billingStatus === 'cancelado') {
            return false;
        }

        return true;
    }

    private function findDuplicateCasesForQuota(int $unitId, string $reference, string $dueDate, ?float $amount = null, ?string $quotaType = null): array
    {
        return CobrancaCase::query()
            ->with(['quotas', 'creator'])
            ->where('unit_id', $unitId)
            ->whereNotIn('situation', ['cancelado', 'pago_encerrado'])
            ->whereHas('quotas', function ($query) use ($reference, $dueDate, $amount, $quotaType) {
                $query->where('reference_label', $reference)
                    ->whereDate('due_date', $dueDate);

                if ($amount !== null) {
                    $query->where(function ($amountQuery) use ($amount) {
                        $amountQuery->where('original_amount', $amount)
                            ->orWhere('updated_amount', $amount);
                    });
                }

                if ($quotaType !== null && $quotaType !== '') {
                    $query->where('status', $quotaType);
                }
            })
            ->orderByDesc('id')
            ->get()
            ->values()
            ->all();
    }

    private function formatCaseForImportPreview(CobrancaCase $case): array
    {
        $openAmount = $case->quotas->sum(function (CobrancaCaseQuota $quota) {
            return (float) ($quota->updated_amount ?? $quota->original_amount ?? 0);
        });

        return [
            'id' => (int) $case->id,
            'os_number' => (string) $case->os_number,
            'status' => $this->workflowStageLabels()[$this->normalizeWorkflowStage((string) $case->workflow_stage)] ?? (string) $case->workflow_stage,
            'situation' => $this->situationLabels()[(string) $case->situation] ?? (string) $case->situation,
            'opened_at' => optional($case->created_at)->format('d/m/Y H:i'),
            'responsible' => (string) ($case->creator?->name ?? ''),
            'quotas_count' => (int) $case->quotas->count(),
            'open_amount' => round($openAmount, 2),
            'last_progress_at' => optional($case->last_progress_at)->format('d/m/Y H:i'),
        ];
    }

    private function normalizeImportDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $serialDate = $this->excelSerialDateToIso($value);
        if ($serialDate !== null) {
            return $serialDate;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $value, $m)) {
            return '20' . $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return null;
    }

    private function formatImportDateInput(string $value): string
    {
        $value = trim($value);
        $normalized = $this->normalizeImportDate($value);
        if ($normalized === null) {
            return $value;
        }

        return (new \DateTimeImmutable($normalized))->format('d/m/Y');
    }

    private function excelSerialDateToIso(string $value): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d{2,6}(?:\.\d+)?$/', $value)) {
            return null;
        }

        $serial = (float) $value;
        if ($serial < 25569 || $serial > 60000) {
            return null;
        }

        try {
            return (new \DateTimeImmutable('1899-12-30'))
                ->modify('+' . (int) floor($serial) . ' days')
                ->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function findOpenCaseForUnit(int $unitId): ?CobrancaCase
    {
        return CobrancaCase::query()
            ->where('unit_id', $unitId)
            ->whereNotIn('situation', ['cancelado', 'pago_encerrado'])
            ->orderByDesc('id')
            ->first();
    }

    private function findActiveCaseWithQuota(int $unitId, string $reference, string $dueDate): ?CobrancaCase
    {
        return CobrancaCase::query()
            ->where('unit_id', $unitId)
            ->whereNotIn('situation', ['cancelado', 'pago_encerrado'])
            ->whereHas('quotas', function ($query) use ($reference, $dueDate) {
                $query->where('reference_label', $reference)
                    ->whereDate('due_date', $dueDate);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function createCaseFromImportRow(CobrancaImportRow $row, CobrancaImportBatch $batch, Request $request, int $userId): CobrancaCase
    {
        $row->loadMissing('unit.owner');
        $unit = $row->unit;
        abort_unless($unit && $unit->owner, 422);

        $chargeDate = $this->normalizeImportDate((string) $row->due_date_input) ?: now()->toDateString();
        $year = (int) date('Y', strtotime($chargeDate));
        $seq = (int) CobrancaCase::query()->where('charge_year', $year)->max('charge_seq') + 1;

        /** @var CobrancaCase $case */
        $case = CobrancaCase::query()->create([
            'charge_year' => $year,
            'charge_seq' => $seq,
            'os_number' => sprintf('COB-%d-%05d', $year, $seq),
            'condominium_id' => $unit->condominium_id,
            'block_id' => $unit->block_id,
            'unit_id' => $unit->id,
            'debtor_entity_id' => $unit->owner_entity_id,
            'debtor_role' => 'owner',
            'debtor_name_snapshot' => (string) $unit->owner->display_name,
            'debtor_document_snapshot' => $unit->owner->cpf_cnpj,
            'debtor_email_snapshot' => collect($unit->owner->emails_json ?? [])->first()['email'] ?? null,
            'debtor_phone_snapshot' => collect($unit->owner->phones_json ?? [])->first()['number'] ?? null,
            'charge_type' => 'extrajudicial',
            'billing_status' => 'a_faturar',
            'situation' => 'processo_aberto',
            'workflow_stage' => 'apto_notificar',
            'calc_base_date' => $chargeDate,
            'notes' => 'OS criada automaticamente via importação do lote #' . $batch->id . ' (' . $batch->original_name . ').',
            'created_by' => $userId,
            'updated_by' => $userId,
            'last_progress_at' => now(),
        ]);

        foreach (collect($unit->owner->emails_json ?? [])->pluck('email')->filter()->values() as $index => $email) {
            CobrancaCaseContact::query()->create([
                'cobranca_case_id' => $case->id,
                'contact_type' => 'email',
                'label' => $index === 0 ? 'Principal' : 'E-mail ' . ($index + 1),
                'value' => Str::limit((string) $email, 190, ''),
                'is_primary' => $index === 0,
                'is_whatsapp' => false,
                'created_at' => now(),
            ]);
        }
        foreach (collect($unit->owner->phones_json ?? [])->pluck('number')->filter()->values() as $index => $phone) {
            CobrancaCaseContact::query()->create([
                'cobranca_case_id' => $case->id,
                'contact_type' => 'phone',
                'label' => $index === 0 ? 'Principal' : 'Telefone ' . ($index + 1),
                'value' => Str::limit((string) $phone, 40, ''),
                'is_primary' => $index === 0,
                'is_whatsapp' => true,
                'created_at' => now(),
            ]);
        }

        $this->recordTimeline($case, 'import_create', 'OS criada automaticamente via importação da planilha de inadimplência.', $request, now());

        return $case;
    }

    private function buildImportReportRows(CobrancaImportBatch $batch): array
    {
        $batch->loadMissing(['rows.unit.condominium', 'rows.unit.block', 'rows.unit.owner', 'rows.cobrancaCase']);
        $labels = $this->importStatusLabels();

        return $batch->rows
            ->sortBy('row_number')
            ->map(function (CobrancaImportRow $row) use ($labels) {
                return [
                    'Linha' => (string) $row->row_number,
                    'Status' => $labels[$row->status] ?? (string) $row->status,
                    'Condominio' => (string) $row->condominium_input,
                    'Bloco' => (string) ($row->block_input ?: ''),
                    'Unidade' => (string) $row->unit_input,
                    'Proprietario' => (string) ($row->owner_input ?: ''),
                    'Referencia' => (string) $row->reference_input,
                    'Vencimento' => (string) $row->due_date_input,
                    'Valor' => $row->amount_value !== null ? number_format((float) $row->amount_value, 2, ',', '.') : '',
                    'Tipo da cota' => (string) ($row->quota_type_input ?: 'taxa_mes'),
                    'OS' => (string) ($row->cobrancaCase?->os_number ?? ''),
                    'Detalhe' => (string) ($row->message ?? ''),
                ];
            })
            ->values()
            ->all();
    }

    private function buildCsvContent(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers, ';');
        foreach ($rows as $row) {
            $ordered = [];
            foreach ($headers as $header) {
                $ordered[] = (string) ($row[$header] ?? '');
            }
            fputcsv($stream, $ordered, ';');
        }
        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $content;
    }

    private function downloadSimpleXlsx(string $filename, string $sheetName, array $headers, array $rows): BinaryFileResponse
    {
        $path = $this->buildSimpleXlsxFile($sheetName, $headers, $rows);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function buildSimpleXlsxFile(string $sheetName, array $headers, array $rows): string
    {
        $dir = storage_path('app/generated/xlsx');
        File::ensureDirectoryExists($dir);
        $path = $dir . DIRECTORY_SEPARATOR . Str::slug($sheetName ?: 'planilha') . '-' . Str::random(8) . '.xlsx';

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Nao foi possivel gerar o arquivo XLSX.');
        }

        $allRows = array_merge([$headers], $rows);
        $sheetXmlRows = [];
        foreach ($allRows as $rowIndex => $row) {
            $cells = [];
            foreach (array_values($row) as $columnIndex => $value) {
                $column = $this->xlsxColumnLabel($columnIndex + 1);
                $reference = $column . ($rowIndex + 1);
                $cells[] = '<c r="' . $reference . '" t="inlineStr"><is><t>' . $this->xmlEscape((string) $value) . '</t></is></c>';
            }
            $sheetXmlRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . implode('', $sheetXmlRows)
            . '</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xmlEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
            . '</styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return $path;
    }

    private function xlsxColumnLabel(int $index): string
    {
        $label = '';
        while ($index > 0) {
            $index--;
            $label = chr(65 + ($index % 26)) . $label;
            $index = intdiv($index, 26);
        }

        return $label;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function renderImportReportPdfResponse(CobrancaImportBatch $batch, array $summary, array $rows): Response|BinaryFileResponse
    {
        $htmlPath = null;
        $pdfPath = null;

        try {
            $dir = storage_path('app/generated/cobranca-import-reports');
            File::ensureDirectoryExists($dir);

            $baseName = 'relatorio-importacao-inadimplencia-' . $batch->id . '-' . now()->format('YmdHis') . '-' . Str::random(6);
            $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
            $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

            File::put($htmlPath, view('pages.cobrancas.import-report-pdf', [
                'batch' => $batch,
                'summary' => $summary,
                'rows' => $rows,
                'statusLabels' => $this->importStatusLabels(),
            ])->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);
                return response(view('pages.cobrancas.import-report-pdf', [
                    'batch' => $batch,
                    'summary' => $summary,
                    'rows' => $rows,
                    'statusLabels' => $this->importStatusLabels(),
                ])->render());
            }

            return response()
                ->file($pdfPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="relatorio-importacao-inadimplencia.pdf"',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable) {
            if ($htmlPath) {
                File::delete($htmlPath);
            }
            if ($pdfPath) {
                File::delete($pdfPath);
            }

            return response(view('pages.cobrancas.import-report-pdf', [
                'batch' => $batch,
                'summary' => $summary,
                'rows' => $rows,
                'statusLabels' => $this->importStatusLabels(),
            ])->render());
        }
    }

    private function invalidNotificationEmails(Request $request): array
    {
        return collect((array) $request->input('emails', []))
            ->pluck('value')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }

    private function normalizeReferenceLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $serialDate = $this->excelSerialDateToIso($value);
        if ($serialDate !== null) {
            return (new \DateTimeImmutable($serialDate))->format('m/Y');
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return $m[2] . '/' . $m[1];
        }
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . '/' . $m[2];
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) === 6) {
            $first = substr($digits, 0, 2);
            if ((int) $first >= 1 && (int) $first <= 12) {
                return $first . '/' . substr($digits, 2, 4);
            }
            return substr($digits, 4, 2) . '/' . substr($digits, 0, 4);
        }

        return $value;
    }

    private function isValidReferenceLabel(string $value): bool
    {
        return (bool) preg_match('/^(0[1-9]|1[0-2])\/\d{4}$/', $value);
    }

    private function formatImportReferenceInput(string $value): string
    {
        $value = trim($value);
        $normalized = $this->normalizeReferenceLabel($value);

        return $normalized !== '' ? $normalized : $value;
    }

    private function normalizeQuotaKind(?string $value): string
    {
        $normalized = $this->normalizeLookupValue((string) $value, false);

        return match ($normalized) {
            'TAXAEXTRA', 'EXTRA' => 'taxa_extra',
            'PARCELAACORDO', 'ACORDO' => 'parcela_acordo',
            default => 'taxa_mes',
        };
    }

    private function billingReportData(Request $request): array
    {
        $filters = [
            'billing_status' => trim((string) $request->input('billing_status', 'a_faturar')),
            'condominium_id' => trim((string) $request->input('condominium_id', '')),
            'charge_type' => trim((string) $request->input('charge_type', '')),
            'billing_date_from' => trim((string) $request->input('billing_date_from', '')),
            'billing_date_to' => trim((string) $request->input('billing_date_to', '')),
        ];

        $query = CobrancaCase::query()
            ->select('cobranca_cases.*')
            ->leftJoin('client_condominiums as billing_condominium_sort', 'billing_condominium_sort.id', '=', 'cobranca_cases.condominium_id')
            ->leftJoin('client_condominium_blocks as billing_block_sort', 'billing_block_sort.id', '=', 'cobranca_cases.block_id')
            ->leftJoin('client_units as billing_unit_sort', 'billing_unit_sort.id', '=', 'cobranca_cases.unit_id')
            ->with(['condominium', 'block', 'unit', 'installments']);

        if ($filters['billing_status'] !== '') {
            $query->where('cobranca_cases.billing_status', $filters['billing_status']);
        }

        if ((int) $filters['condominium_id'] > 0) {
            $query->where('cobranca_cases.condominium_id', (int) $filters['condominium_id']);
        }

        if ($filters['charge_type'] !== '') {
            $query->where('cobranca_cases.charge_type', $filters['charge_type']);
        }

        if ($filters['billing_date_from'] !== '') {
            $query->whereDate('cobranca_cases.billing_date', '>=', $filters['billing_date_from']);
        }

        if ($filters['billing_date_to'] !== '') {
            $query->whereDate('cobranca_cases.billing_date', '<=', $filters['billing_date_to']);
        }

        $cases = $query
            ->orderBy('billing_condominium_sort.name')
            ->orderBy('billing_block_sort.name')
            ->orderBy('billing_unit_sort.unit_number')
            ->orderBy('cobranca_cases.os_number')
            ->get();

        $rows = $cases->map(fn (CobrancaCase $case) => $this->billingReportRow($case))->values();

        $groups = $rows
            ->groupBy('condominium_key')
            ->map(function ($condominiumRows) {
                $condominiumRows = collect($condominiumRows);

                return [
                    'condominium' => (string) ($condominiumRows->first()['condominium'] ?? 'Condomínio não vinculado'),
                    'totals' => $this->billingRowsTotals($condominiumRows),
                    'blocks' => $condominiumRows
                        ->groupBy('block_key')
                        ->map(function ($blockRows) {
                            $blockRows = collect($blockRows);

                            return [
                                'block' => (string) ($blockRows->first()['block'] ?? 'Sem bloco'),
                                'rows' => $blockRows->values(),
                                'totals' => $this->billingRowsTotals($blockRows),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();

        return [
            'filters' => $filters,
            'filterOptions' => [
                'condominiums' => DB::table('client_condominiums')->orderBy('name')->get(['id', 'name']),
                'chargeTypes' => $this->chargeTypeLabels(),
                'billingStatuses' => $this->billingStatusLabels(),
            ],
            'rows' => $rows,
            'groups' => $groups,
            'totals' => $this->billingRowsTotals($rows),
        ];
    }

    private function billingReportRow(CobrancaCase $case): array
    {
        $paid = $this->billingPaidSnapshot($case);
        $agreementCents = $this->moneyToCents($case->agreement_total ?? 0);
        $paidCents = (int) $paid['amount_cents'];
        $feesCents = $this->moneyToCents($case->fees_amount ?? 0);
        $projectedCents = max(0, $agreementCents - $paidCents);

        $condominium = $case->condominium?->name ?: 'Condomínio não vinculado';
        $block = $case->block?->name ?: 'Sem bloco';
        $unit = $case->unit?->unit_label ?: $case->unit?->unit_number ?: '-';

        return [
            'id' => (int) $case->id,
            'os_number' => (string) $case->os_number,
            'condominium_key' => (string) ($case->condominium_id ?: 0) . '|' . $condominium,
            'condominium' => $condominium,
            'block_key' => (string) ($case->block_id ?: 0) . '|' . $block,
            'block' => $block,
            'unit' => (string) $unit,
            'debtor' => (string) ($case->debtor_name_snapshot ?: '-'),
            'charge_type' => (string) $case->charge_type,
            'charge_type_label' => $this->chargeTypeLabels()[$case->charge_type] ?? (string) $case->charge_type,
            'billing_status' => (string) $case->billing_status,
            'billing_status_label' => $this->billingStatusLabels()[$case->billing_status] ?? (string) $case->billing_status,
            'billing_date' => optional($case->billing_date)->format('d/m/Y'),
            'agreement_total' => $this->decimalFromCents($agreementCents),
            'paid_amount' => $this->decimalFromCents($paidCents),
            'paid_label' => (string) $paid['label'],
            'projected_amount' => $this->decimalFromCents($projectedCents),
            'fees_amount' => $this->decimalFromCents($feesCents),
            'installments_count' => (int) $case->installments->count(),
        ];
    }

    private function billingPaidSnapshot(CobrancaCase $case): array
    {
        $entryCents = $this->moneyToCents($case->entry_amount ?? 0);
        if ($entryCents > 0) {
            return [
                'amount_cents' => $entryCents,
                'label' => 'Entrada',
            ];
        }

        $entryInstallment = $case->installments->first(fn ($installment) => $installment->installment_type === 'entrada');
        if ($entryInstallment && $this->moneyToCents($entryInstallment->amount ?? 0) > 0) {
            return [
                'amount_cents' => $this->moneyToCents($entryInstallment->amount ?? 0),
                'label' => 'Entrada nas parcelas',
            ];
        }

        if ($case->installments->count() === 1) {
            return [
                'amount_cents' => $this->moneyToCents($case->installments->first()->amount ?? 0),
                'label' => 'Parcela única',
            ];
        }

        $firstPaid = $case->installments->first(fn ($installment) => $installment->status === 'paga');
        if ($firstPaid && $this->moneyToCents($firstPaid->amount ?? 0) > 0) {
            return [
                'amount_cents' => $this->moneyToCents($firstPaid->amount ?? 0),
                'label' => '1ª parcela paga',
            ];
        }

        return [
            'amount_cents' => 0,
            'label' => 'Sem pagamento registrado',
        ];
    }

    private function billingRowsTotals($rows): array
    {
        $rows = collect($rows);

        return [
            'cases_count' => $rows->count(),
            'agreement_total' => (float) $rows->sum('agreement_total'),
            'paid_amount' => (float) $rows->sum('paid_amount'),
            'projected_amount' => (float) $rows->sum('projected_amount'),
            'fees_amount' => (float) $rows->sum('fees_amount'),
        ];
    }

    private function billingReportPdfResponse(array $viewData): ?BinaryFileResponse
    {
        $htmlPath = null;
        $pdfPath = null;

        try {
            $dir = storage_path('app/generated/cobranca-billing-reports');
            File::ensureDirectoryExists($dir);

            $baseName = 'relatorio-faturamento-cobranca-' . now()->format('YmdHis') . '-' . Str::random(6);
            $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
            $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

            File::put($htmlPath, view('pages.cobrancas.billing-report-pdf', array_merge($viewData, [
                'autoPrint' => false,
                'pdfMode' => true,
            ]))->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);
                return null;
            }

            return response()
                ->file($pdfPath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="relatorio-faturamento-cobranca.pdf"',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable) {
            if ($htmlPath) {
                File::delete($htmlPath);
            }
            if ($pdfPath) {
                File::delete($pdfPath);
            }

            return null;
        }
    }

    private function filterOptions(): array
    {
        return [
            'condominiums' => DB::table('client_condominiums')->orderBy('name')->get(['id', 'name']),
            'chargeTypes' => $this->chargeTypeLabels(),
            'workflowStages' => $this->workflowStageLabels(),
            'situations' => $this->situationLabels(),
            'billingStatuses' => $this->billingStatusLabels(),
        ];
    }

    private function n8nPayload(CobrancaCase $case): array
    {
        $case->loadMissing(['condominium', 'block', 'unit', 'contacts', 'quotas', 'installments']);
        return [
            'os_number' => $case->os_number,
            'tipo_cobranca' => $case->charge_type,
            'condominio' => $case->condominium?->name,
            'bloco' => $case->block?->name,
            'unidade' => $case->unit?->unit_label ?: $case->unit?->unit_number,
            'devedor' => [
                'nome' => $case->debtor_name_snapshot,
                'documento' => $case->debtor_document_snapshot,
                'emails' => $case->contacts->where('contact_type', 'email')->pluck('value')->values()->all(),
                'telefones' => $case->contacts->where('contact_type', 'phone')->map(fn ($item) => [
                    'numero' => $item->value,
                    'whatsapp' => $item->is_whatsapp,
                ])->values()->all(),
            ],
            'valores' => [
                'acordo_total' => (float) ($case->agreement_total ?? 0),
                'entrada' => (float) ($case->entry_amount ?? 0),
                'honorarios' => (float) ($case->fees_amount ?? 0),
                'base_calculo' => optional($case->calc_base_date)->format('Y-m-d'),
            ],
            'quotas' => $case->quotas->map(fn ($item) => [
                'referencia' => $item->reference_label,
                'vencimento' => optional($item->due_date)->format('Y-m-d'),
                'valor_original' => (float) $item->original_amount,
                'valor_atualizado' => (float) ($item->updated_amount ?? $item->original_amount),
                'status' => $item->status,
            ])->values()->all(),
            'parcelas' => $case->installments->map(fn ($item) => [
                'label' => $item->label,
                'tipo' => $item->installment_type,
                'numero' => $item->installment_number,
                'vencimento' => optional($item->due_date)->format('Y-m-d'),
                'valor' => (float) $item->amount,
                'status' => $item->status,
            ])->values()->all(),
            'link_interno' => route('cobrancas.show', $case),
        ];
    }

    private function normalizeUploadedFiles(mixed $value): array
    {
        if ($value instanceof UploadedFile) {
            return [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        $files = [];
        array_walk_recursive($value, function ($item) use (&$files) {
            if ($item instanceof UploadedFile) {
                $files[] = $item;
            }
        });
        return $files;
    }

    private function safeUploadedFileSize(UploadedFile $file): int
    {
        try {
            $realPath = $file->getRealPath();
            if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
                return (int) (@filesize($realPath) ?: 0);
            }
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function moneyToDb(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = preg_replace('/[^\d,.-]/', '', (string) $value) ?: '';
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);
        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }

    private function moneyToCents(mixed $value): int
    {
        return (int) round(((float) ($value ?? 0)) * 100);
    }

    private function centsToMoney(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    private function logAction(Request $request, string $action, int $entityId, string $details): void
    {
        $user = AncoraAuth::user($request);
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'user_email' => $user?->email ?? 'desconhecido',
            'action' => $action,
            'entity_type' => 'cobrancas',
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
        $request->attributes->set('audit.skip_generic', true);
    }

    private function chargeTypeLabels(): array
    {
        return [
            'extrajudicial' => 'Cobrança extrajudicial',
            'judicial' => 'Cobrança judicial',
        ];
    }

    private function workflowStageLabels(): array
    {
        return [
            'triagem' => 'Triagem',
            'apto_notificar' => 'Apto para notificar',
            'em_negociacao' => 'Em negociação',
            'aguardando_assinatura' => 'Aguardando assinatura',
            'acordo_ativo' => 'Acordo ativo',
            'acordo_inadimplido' => 'Acordo inadimplido',
            'apto_judicializar' => 'Apto para judicializar',
            'judicializado' => 'Judicializado',
            'pago_encerrado' => 'Pago / encerrado',
            'cancelado' => 'Cancelado',
        ];
    }

    private function normalizeWorkflowStage(string $stage): string
    {
        $stage = trim($stage) ?: 'triagem';

        return match ($stage) {
            'notificado' => 'apto_notificar',
            'sem_retorno', 'aguardando_termo' => 'em_negociacao',
            'aguardando_boletos' => 'acordo_ativo',
            'encerrado' => 'pago_encerrado',
            default => array_key_exists($stage, $this->workflowStageLabels()) ? $stage : 'triagem',
        };
    }

    private function situationFromWorkflowStage(string $stage): string
    {
        return match ($this->normalizeWorkflowStage($stage)) {
            'acordo_ativo' => 'em_pagamento_acordo',
            'acordo_inadimplido' => 'acordo_nao_pago',
            'judicializado' => 'ajuizado',
            'pago_encerrado' => 'pago_encerrado',
            'cancelado' => 'cancelado',
            default => 'processo_aberto',
        };
    }

    private function workflowStageFromSituation(?string $situation): string
    {
        return match ((string) $situation) {
            'acordo_nao_pago' => 'acordo_inadimplido',
            'ajuizado' => 'judicializado',
            'cancelado' => 'cancelado',
            'em_pagamento_acordo', 'acordo_renegociado' => 'acordo_ativo',
            'pago_encerrado' => 'pago_encerrado',
            default => 'triagem',
        };
    }

    private function situationLabels(): array
    {
        return [
            'acordo_nao_pago' => 'Acordo não pago',
            'acordo_renegociado' => 'Acordo renegociado',
            'ajuizado' => 'Ajuizado',
            'cancelado' => 'Cancelado',
            'em_pagamento_acordo' => 'Em pagamento de acordo',
            'pago_encerrado' => 'Pago / encerrado',
            'processo_aberto' => 'Processo de cobrança em aberto',
        ];
    }

    private function billingStatusLabels(): array
    {
        return [
            'a_faturar' => 'A faturar',
            'faturado' => 'Faturado',
            'contrato_fixo' => 'Contrato fixo',
            'cancelado' => 'Cancelado',
        ];
    }

    private function entryStatusLabels(): array
    {
        return [
            'boleto_enviado' => 'Boleto enviado ao condômino',
            'boleto_solicitado' => 'Boleto solicitado à administradora',
            'pago' => 'Pago',
        ];
    }

    private function quotaStatusLabels(): array
    {
        return [
            'taxa_mes' => 'Taxa do mês',
            'taxa_extra' => 'Taxa extra',
            'parcela_acordo' => 'Parcela de acordo',
        ];
    }

    private function installmentStatusLabels(): array
    {
        return [
            'pendente' => 'Pendente',
            'boleto_solicitado' => 'Boleto solicitado',
            'boleto_enviado' => 'Boleto enviado',
            'paga' => 'Paga',
            'atrasada' => 'Atrasada',
        ];
    }
}
