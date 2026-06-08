<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Models\ClientCondominium;
use App\Models\ClientAttachment;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractCategory;
use App\Models\ContractTemplate;
use App\Models\ContractVersion;
use App\Models\FinancialAccount;
use App\Models\ProcessCase;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ContractFinancialService;
use App\Services\ContractPdfService;
use App\Services\ContractRenderService;
use App\Support\AncoraAuth;
use App\Support\Contracts\ContractCatalog;
use App\Support\Contracts\ContractVariableCatalog;
use App\Support\ContractSettings;
use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractRenderService $renderService,
        private readonly ContractPdfService $pdfService,
        private readonly ContractFinancialService $contractFinancialService,
    ) {
    }

    public function dashboard(Request $request): View
    {
        $year = max(2024, (int) $request->integer('year', now()->year));
        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $alertDays = max(1, (int) ContractSettings::get('due_alert_days', '30'));

        $contracts = Contract::query()
            ->with(['category', 'client', 'condominium', 'responsible', 'syndic', 'financialAccount'])
            ->get();
        $templatesCount = ContractTemplate::query()->where('is_active', true)->count();

        $summary = [
            'total' => $contracts->count(),
            'ativos' => $contracts->where('status', 'ativo')->count(),
            'rascunhos' => $contracts->where('status', 'rascunho')->count(),
            'vencidos' => $contracts->filter(fn (Contract $item) => $this->isExpired($item))->count(),
            'proximos' => $contracts->filter(fn (Contract $item) => $this->isUpcoming($item, $alertDays))->count(),
            'rescindidos' => $contracts->where('status', 'rescindido')->count(),
            'assinatura' => $contracts->where('status', 'aguardando_assinatura')->count(),
            'templates' => $templatesCount,
            'month_label' => now()->format('m/Y'),
        ];

        $monthCounts = array_fill(0, 12, 0);
        foreach ($contracts as $contract) {
            if ($contract->created_at && (int) $contract->created_at->year === $year) {
                $monthCounts[$contract->created_at->month - 1]++;
            }
        }

        $upcomingBuckets = [];
        foreach ($contracts->filter(fn (Contract $item) => $this->isUpcoming($item, 180)) as $item) {
            $key = optional($item->end_date)->format('m/Y');
            if (!$key) {
                continue;
            }

            $upcomingBuckets[$key] = ($upcomingBuckets[$key] ?? 0) + 1;
        }

        $alerts = [
            'upcoming' => $contracts->filter(fn (Contract $item) => $this->isUpcoming($item, $alertDays))->sortBy('end_date')->take(8)->values(),
            'upcoming_adjustments' => $contracts->filter(fn (Contract $item) => $this->isAdjustmentUpcoming($item, $alertDays))->sortBy('next_adjustment_date')->take(8)->values(),
            'without_pdf' => $contracts->filter(fn (Contract $item) => !$item->final_pdf_path)->take(8)->values(),
            'drafts' => $contracts->where('status', 'rascunho')->take(8)->values(),
            'without_client' => $contracts->filter(fn (Contract $item) => !$item->client_id && !$item->condominium_id && !$item->syndico_entity_id)->take(8)->values(),
            'awaiting_signature' => $contracts->where('status', 'aguardando_assinatura')->take(8)->values(),
        ];

        return view('pages.contratos.dashboard', [
            'title' => 'Dashboard de Contratos',
            'year' => $year,
            'years' => collect([$year - 1, $year, $year + 1])->unique()->values(),
            'summary' => $summary,
            'alerts' => $alerts,
            'statusDistribution' => $contracts->groupBy('status')->map(fn ($group, $status) => [
                'label' => ContractCatalog::statuses()[$status] ?? Str::headline($status),
                'count' => $group->count(),
            ])->values()->all(),
            'typeDistribution' => $contracts->groupBy('type')->map(fn ($group, $type) => [
                'label' => $type,
                'count' => $group->count(),
            ])->values()->all(),
            'chartData' => [
                'labels' => $monthLabels,
                'monthCounts' => $monthCounts,
                'upcomingLabels' => array_keys($upcomingBuckets),
                'upcomingCounts' => array_values($upcomingBuckets),
            ],
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);
        $sort = in_array($request->input('sort'), ['code', 'title', 'client', 'condominium', 'type', 'value', 'start', 'end', 'status', 'responsible'], true)
            ? (string) $request->input('sort')
            : 'start';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $query = Contract::query()
            ->with(['category', 'client', 'condominium', 'responsible', 'syndic', 'financialAccount'])
            ->select('contracts.*');

        if ($filters['scope'] === 'trash') {
            $query->onlyTrashed();
        }

        $this->applyFilters($query, $filters);

        match ($sort) {
            'code' => $query->orderBy('code', $direction),
            'title' => $query->orderBy('title', $direction),
            'client' => $query->leftJoin('client_entities as contract_client_sort', 'contract_client_sort.id', '=', 'contracts.client_id')->orderBy('contract_client_sort.display_name', $direction),
            'condominium' => $query->leftJoin('client_condominiums as contract_condo_sort', 'contract_condo_sort.id', '=', 'contracts.condominium_id')->orderBy('contract_condo_sort.name', $direction),
            'type' => $query->orderBy('type', $direction),
            'value' => $query->orderByRaw('COALESCE(contract_value, total_value, monthly_value, 0) ' . $direction),
            'end' => $query->orderBy('end_date', $direction)->orderByDesc('id'),
            'status' => $query->orderBy('status', $direction)->orderByDesc('id'),
            'responsible' => $query->leftJoin('users as contract_user_sort', 'contract_user_sort.id', '=', 'contracts.responsible_user_id')->orderBy('contract_user_sort.name', $direction),
            default => $query->orderBy('start_date', $direction)->orderByDesc('id'),
        };

        $items = $query->paginate(15)->withQueryString();

        return view('pages.contratos.index', array_merge($this->formOptions(), [
            'title' => 'Contratos',
            'items' => $items,
            'filters' => $filters,
            'sortState' => ['sort' => $sort, 'direction' => $direction],
            'statusLabels' => ContractCatalog::statuses(),
        ]));
    }

    public function create(Request $request): View
    {
        $draft = [
            'status' => ContractSettings::get('default_status', 'rascunho'),
            'page_orientation' => 'portrait',
            'indefinite_term' => true,
        ];

        $title = 'Novo contrato';
        $parent = $request->filled('parent')
            ? Contract::query()->find((int) $request->input('parent'))
            : null;

        // Quando criado a partir de um contrato existente, ja nasce como aditivo vinculado,
        // herdando as partes do contrato-pai para manter a coerencia do vinculo.
        if ($parent) {
            $title = 'Novo aditivo';
            $draft = array_merge($draft, [
                'parent_contract_id' => $parent->id,
                'type' => 'Aditivo contratual',
                'client_id' => $parent->client_id,
                'condominium_id' => $parent->condominium_id,
                'unit_id' => $parent->unit_id,
                'syndico_entity_id' => $parent->syndico_entity_id,
                'category_id' => $parent->category_id,
                'responsible_user_id' => $parent->responsible_user_id,
            ]);
        }

        return view('pages.contratos.form', array_merge($this->formOptions(), [
            'title' => $title,
            'mode' => 'create',
            'item' => null,
            'draft' => $draft,
            'parentContract' => $parent,
            'previewHtml' => old('content_html', ''),
            'formAlerts' => [],
            'pdfAppendixAttachments' => collect(),
            'existingReceivablesCount' => 0,
        ]));
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $template = $request->filled('template_id')
            ? ContractTemplate::query()->find((int) $request->input('template_id'))
            : null;
        $payload = $this->applyConditionalContractPayload($this->normalizedPayload($request));
        $payload['title'] = $this->resolvedTitle($template, $payload);

        if ($response = $this->contractSaveGuardResponse($request, $payload)) {
            return $response;
        }

        if (!$this->autoCodeEnabled() && trim((string) ($payload['code'] ?? '')) === '') {
            return back()->withInput()->with('error', 'Informe o codigo interno do contrato quando a numeracao automatica estiver desativada.');
        }

        if (trim((string) ($payload['title'] ?? '')) === '') {
            return back()->withInput()->with('error', 'Selecione um template com titulo padrao ou informe o titulo do contrato.');
        }

        if ($payload['code'] && Contract::query()->where('code', $payload['code'])->exists()) {
            return back()->withInput()->with('error', 'Ja existe um contrato com esse codigo interno.');
        }

        if (trim((string) ($payload['content_html'] ?? '')) === '' && $template) {
            $payload['content_html'] = $this->renderService->renderTemplate($template, array_merge($request->all(), $payload));
        }

        if ($request->boolean('generate_pdf_now') && !$template) {
            return back()->withInput()->with('error', 'Selecione um template antes de gerar o PDF do contrato.');
        }

        $financialSync = ['created' => 0, 'skipped' => 0, 'deleted' => 0, 'mode' => null];

        try {
            $contract = DB::transaction(function () use ($payload, $user, $request, &$financialSync) {
                $contract = Contract::query()->create(array_merge($payload, [
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]));

                if ($this->autoCodeEnabled() && !$contract->code) {
                    $contract->forceFill(['code' => $this->generatedCode($contract)])->save();
                }

                $financialSync = $this->syncContractFinancialEntriesAfterSave(
                    $contract->fresh(),
                    null,
                    $user->id,
                    (string) $request->input('financial_entries_action', '')
                );

                if ($request->boolean('generate_pdf_now')) {
                    $freshContract = $contract->fresh(['template', 'client', 'condominium', 'syndic', 'unit', 'responsible']);
                    $selectedAttachments = $this->selectedPdfAppendixAttachments(
                        $freshContract,
                        (array) $request->input('pdf_attachment_ids', [])
                    );

                    $this->pdfService->generate(
                        $freshContract,
                        $user->id,
                        (string) $request->input('version_notes', 'Versao inicial.'),
                        $selectedAttachments
                    );
                }

                return $contract;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Nao foi possivel salvar o contrato: ' . $e->getMessage());
        }

        $message = $request->boolean('generate_pdf_now') ? 'Contrato salvo e PDF gerado com sucesso.' : 'Contrato salvo com sucesso.';
        $message .= $this->contractFinancialSyncMessage($financialSync);

        return redirect()
            ->route('contratos.show', $contract)
            ->with('success', $message);
    }

    public function show(Request $request, Contract $contrato): View
    {
        $activeTab = in_array($request->query('tab'), ['historico', 'anexos', 'assinaturas'], true) ? $request->query('tab') : 'resumo';
        $signatureStorageReady = $this->signatureStorageReady();
        $relations = [
            'category',
            'template',
            'client',
            'condominium.syndic',
            'syndic',
            'unit.block',
            'proposal',
            'process',
            'responsible',
            'financialAccount',
            'creator',
            'updater',
            'versions.generator',
            'attachments.uploader',
            'parentContract',
            'amendments',
        ];
        if ($signatureStorageReady) {
            $relations = array_merge($relations, [
                'signatureRequests.signers',
                'signatureRequests.events.signer',
                'signatureRequests.creator',
                'signatureRequests.updater',
                'signatureRequests.documentVersion',
            ]);
        }
        $contrato->load($relations);

        return view('pages.contratos.show', [
            'title' => $contrato->code ?: $contrato->title,
            'item' => $contrato,
            'activeTab' => $activeTab,
            'statusLabels' => ContractCatalog::statuses(),
            'paymentMethodLabels' => FinancialCatalog::paymentMethods(),
            'signatureStorageReady' => $signatureStorageReady,
            'contractAlerts' => $this->contractAlerts($contrato),
            'pdfAppendixAttachments' => $this->availablePdfAppendixAttachments($contrato),
        ]);
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

    public function edit(Contract $contrato): View
    {
        $contrato->load(['category', 'template', 'client', 'condominium.syndic', 'syndic', 'unit', 'responsible', 'financialAccount'])
            ->loadCount('receivables');

        return view('pages.contratos.form', array_merge($this->formOptions(), [
            'title' => 'Editar contrato',
            'mode' => 'edit',
            'item' => $contrato,
            'draft' => $contrato->toArray(),
            'previewHtml' => old('content_html', $contrato->content_html),
            'formAlerts' => $this->contractAlerts($contrato),
            'pdfAppendixAttachments' => $this->availablePdfAppendixAttachments($contrato),
            'existingReceivablesCount' => (int) ($contrato->receivables_count ?? 0),
        ]));
    }

    public function update(UpdateContractRequest $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $template = $request->filled('template_id')
            ? ContractTemplate::query()->find((int) $request->input('template_id'))
            : null;
        $payload = $this->applyConditionalContractPayload($this->normalizedPayload($request, $contrato), $contrato);
        $payload['title'] = $this->resolvedTitle($template, $payload, $contrato);

        if ($response = $this->contractSaveGuardResponse($request, $payload, $contrato)) {
            return $response;
        }

        if (!$this->autoCodeEnabled() && trim((string) ($payload['code'] ?? '')) === '') {
            return back()->withInput()->with('error', 'Informe o codigo interno do contrato quando a numeracao automatica estiver desativada.');
        }

        if (trim((string) ($payload['title'] ?? '')) === '') {
            return back()->withInput()->with('error', 'Selecione um template com titulo padrao ou informe o titulo do contrato.');
        }

        if (!empty($payload['code']) && Contract::query()->where('code', $payload['code'])->whereKeyNot($contrato->id)->exists()) {
            return back()->withInput()->with('error', 'Ja existe outro contrato com esse codigo interno.');
        }

        if (trim((string) ($payload['content_html'] ?? '')) === '' && $template) {
            $payload['content_html'] = $this->renderService->renderTemplate($template, array_merge($request->all(), $payload));
        }

        if ($request->boolean('generate_pdf_now') && !$template) {
            return back()->withInput()->with('error', 'Selecione um template antes de gerar o PDF do contrato.');
        }

        $financialSync = ['created' => 0, 'skipped' => 0, 'deleted' => 0, 'mode' => null];
        $originalContract = clone $contrato;

        try {
            DB::transaction(function () use ($contrato, $payload, $user, $request, $originalContract, &$financialSync) {
                $contrato->update(array_merge($payload, ['updated_by' => $user->id]));

                $financialSync = $this->syncContractFinancialEntriesAfterSave(
                    $contrato->fresh(),
                    $originalContract,
                    $user->id,
                    (string) $request->input('financial_entries_action', '')
                );

                if ($request->boolean('generate_pdf_now')) {
                    $freshContract = $contrato->fresh(['template', 'client', 'condominium', 'syndic', 'unit', 'responsible']);
                    $selectedAttachments = $this->selectedPdfAppendixAttachments(
                        $freshContract,
                        (array) $request->input('pdf_attachment_ids', [])
                    );

                    $this->pdfService->generate(
                        $freshContract,
                        $user->id,
                        (string) $request->input('version_notes', 'Nova versao gerada pela edicao do contrato.'),
                        $selectedAttachments
                    );
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Nao foi possivel atualizar o contrato: ' . $e->getMessage());
        }

        $message = $request->boolean('generate_pdf_now') ? 'Contrato atualizado e nova versao de PDF gerada.' : 'Contrato atualizado com sucesso.';
        $message .= $this->contractFinancialSyncMessage($financialSync);

        return redirect()
            ->route('contratos.show', $contrato)
            ->with('success', $message);
    }

    public function destroy(Contract $contrato): RedirectResponse
    {
        $contrato->delete();

        return redirect()->route('contratos.index')->with('success', 'Contrato enviado para a lixeira com sucesso.');
    }

    public function restore(int $contractId): RedirectResponse
    {
        $contract = Contract::withTrashed()->findOrFail($contractId);
        $contract->restore();

        return redirect()
            ->route('contratos.index', ['scope' => 'trash'])
            ->with('success', 'Contrato restaurado com sucesso.');
    }

    public function duplicate(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $clone = null;

        DB::transaction(function () use ($contrato, $user, &$clone) {
            $clone = $contrato->replicate([
                'code',
                'status',
                'final_pdf_path',
                'final_pdf_generated_at',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);
            $clone->title = $contrato->title . ' (copia)';
            $clone->status = ContractSettings::get('default_status', 'rascunho');
            $clone->code = null;
            $clone->created_by = $user->id;
            $clone->updated_by = $user->id;
            $clone->save();

            if ($this->autoCodeEnabled()) {
                $clone->forceFill(['code' => $this->generatedCode($clone)])->save();
            }
        });

        return redirect()->route('contratos.edit', $clone)->with('success', 'Contrato duplicado. Revise os dados antes de salvar a nova versao.');
    }

    public function archive(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        $contrato->update([
            'status' => 'arquivado',
            'updated_by' => $user?->id,
        ]);

        return redirect()->route('contratos.show', $contrato)->with('success', 'Contrato arquivado com sucesso.');
    }

    public function rescind(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        $contrato->update([
            'status' => 'rescindido',
            'updated_by' => $user?->id,
        ]);

        return redirect()->route('contratos.show', $contrato)->with('success', 'Contrato marcado como rescindido.');
    }

    public function generateSuccessFee(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);
        abort_unless($contrato->billing_type === 'honorarios_sobre_exito', 422);

        $validated = $request->validate([
            'base_amount' => ['required', 'string', 'max:40'],
            'success_fee_percentage' => ['required', 'string', 'max:20'],
            'due_date' => ['nullable', 'date'],
        ]);

        $base = (float) $this->renderService->moneyFromInput($validated['base_amount']);
        $percentage = (float) $this->renderService->moneyFromInput($validated['success_fee_percentage']);

        if ($base <= 0 || $percentage <= 0) {
            return back()->with('error', 'Informe a base do ganho e o percentual de exito, ambos maiores que zero.');
        }

        try {
            $receivable = $this->contractFinancialService->generateSuccessFeeReceivable(
                $contrato,
                $base,
                $percentage,
                $request->filled('due_date') ? Carbon::parse((string) $request->input('due_date')) : now(),
                $user->id
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Nao foi possivel gerar o honorario de exito: ' . $e->getMessage());
        }

        return back()->with('success', 'Honorario de exito gerado com sucesso: ' . ($receivable->code ?: ('#' . $receivable->id)) . '.');
    }

    public function resolvePreview(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'content_html' => ['nullable', 'string'],
        ]);

        $template = $request->filled('template_id')
            ? ContractTemplate::query()->find((int) $request->input('template_id'))
            : null;

        $html = $this->normalizedEditorHtml($request->input('content_html'));
        if ($html === '' && !$template) {
            return response()->json(['message' => 'Selecione um template para carregar o preview.'], 422);
        }

        $attributes = array_merge($request->all(), [
            'title' => $this->resolvedTitle($template, $request->all()),
            'content_html' => $html,
        ]);

        return response()->json([
            'html' => $this->renderService->renderTemplate($template, $attributes, $html !== '' ? $html : null),
        ]);
    }

    public function generatePdf(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $request->validate([
            'version_notes' => ['nullable', 'string', 'max:255'],
            'pdf_attachment_ids' => ['nullable', 'array'],
            'pdf_attachment_ids.*' => ['integer'],
        ]);

        if (!$contrato->template_id) {
            return redirect()->route('contratos.show', $contrato)->with('error', 'Selecione um template no contrato antes de gerar o PDF.');
        }

        if (trim((string) $contrato->content_html) === '') {
            return redirect()->route('contratos.edit', $contrato)->with('error', 'O contrato ainda nao possui conteudo editavel salvo.');
        }

        $selectedAttachments = $this->selectedPdfAppendixAttachments(
            $contrato,
            (array) $request->input('pdf_attachment_ids', [])
        );

        try {
            $version = $this->pdfService->generate(
                $contrato,
                $user->id,
                trim((string) $request->input('version_notes', 'Versao gerada manualmente.')) ?: 'Versao gerada manualmente.',
                $selectedAttachments
            );
        } catch (\Throwable $e) {
            return redirect()->route('contratos.show', $contrato)->with('error', 'Nao foi possivel gerar o PDF: ' . $e->getMessage());
        }

        return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'historico'])->with('success', 'PDF gerado com sucesso na versao v' . $version->version_number . '.');
    }

    public function downloadPdf(Contract $contrato): BinaryFileResponse|RedirectResponse
    {
        $path = $this->pdfService->absolutePath($contrato->final_pdf_path);
        if (!$path) {
            return redirect()->route('contratos.show', $contrato)->with('error', 'Este contrato ainda nao possui PDF final gerado.');
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }

    public function viewVersion(Contract $contrato, ContractVersion $version): BinaryFileResponse|RedirectResponse
    {
        abort_if((int) $version->contract_id !== (int) $contrato->id, 404);

        $path = $this->pdfService->absolutePath($version->pdf_path);
        if (!$path) {
            return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'historico'])->with('error', 'O arquivo PDF desta versao nao foi encontrado.');
        }

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }

    public function downloadVersion(Contract $contrato, ContractVersion $version): BinaryFileResponse|RedirectResponse
    {
        abort_if((int) $version->contract_id !== (int) $contrato->id, 404);

        $path = $this->pdfService->absolutePath($version->pdf_path);
        if (!$path) {
            return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'historico'])->with('error', 'O arquivo PDF desta versao nao foi encontrado.');
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }

    public function uploadAttachment(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $request->validate([
            'file_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,webp,doc,docx,xls,xlsx,csv,txt'],
        ]);

        $dir = storage_path('app/public/contracts/' . $contrato->id . '/attachments');
        File::ensureDirectoryExists($dir);

        foreach ((array) $request->file('files', []) as $file) {
            $stored = now()->format('Ymd_His') . '_' . Str::random(10) . '.' . strtolower((string) $file->getClientOriginalExtension());
            $file->move($dir, $stored);

            ContractAttachment::query()->create([
                'contract_id' => $contrato->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $stored,
                'relative_path' => 'contracts/' . $contrato->id . '/attachments/' . $stored,
                'file_type' => trim((string) $request->input('file_type', 'outro')) ?: 'outro',
                'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
                'file_size' => (int) $file->getSize(),
                'description' => trim((string) $request->input('description', '')) ?: null,
                'uploaded_by' => $user->id,
            ]);
        }

        return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'anexos'])->with('success', 'Anexo(s) enviado(s) com sucesso.');
    }

    public function downloadAttachment(Contract $contrato, ContractAttachment $attachment): BinaryFileResponse
    {
        abort_if((int) $attachment->contract_id !== (int) $contrato->id, 404);

        $path = storage_path('app/public/' . ltrim((string) $attachment->relative_path, '/'));
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->original_name, ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']);
    }

    public function deleteAttachment(Contract $contrato, ContractAttachment $attachment): RedirectResponse
    {
        abort_if((int) $attachment->contract_id !== (int) $contrato->id, 404);

        $path = storage_path('app/public/' . ltrim((string) $attachment->relative_path, '/'));
        if (is_file($path)) {
            File::delete($path);
        }

        $attachment->delete();

        return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'anexos'])->with('success', 'Anexo excluido com sucesso.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => ContractCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'templates' => ContractTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'clients' => ClientEntity::query()->where('is_active', true)->orderBy('display_name')->get(['id', 'display_name']),
            'condominiums' => ClientCondominium::query()->with('syndic')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'syndico_entity_id']),
            'syndics' => ClientEntity::query()
                ->where(function (Builder $query): void {
                    $query
                        ->whereRaw('LOWER(COALESCE(role_tag, "")) like ?', ['%sindico%'])
                        ->orWhereRaw('LOWER(COALESCE(role_tag, "")) like ?', ['%syndic%']);
                })
                ->where(function (Builder $query): void {
                    $query
                        ->where('is_active', true)
                        ->orWhereNull('is_active');
                })
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'legal_name', 'cpf_cnpj', 'entity_type']),
            'units' => ClientUnit::query()->with(['condominium', 'block'])->orderBy('unit_number')->get(),
            'proposals' => Proposal::query()->orderByDesc('id')->limit(300)->get(['id', 'proposal_code', 'client_name']),
            'processes' => class_exists(ProcessCase::class)
                ? ProcessCase::query()->orderByDesc('id')->limit(300)->get(['id', 'process_number', 'client_name_snapshot'])
                : collect(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'financialAccounts' => Schema::hasTable('financial_accounts')
                ? FinancialAccount::query()->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->get(['id', 'name', 'bank_name', 'account_number'])
                : collect(),
            'paymentMethods' => FinancialCatalog::paymentMethods(),
            'statusLabels' => ContractCatalog::statuses(),
            'typeOptions' => ContractCatalog::types(),
            'billingTypes' => ContractCatalog::billingTypes(),
            'recurrenceOptions' => ContractCatalog::recurrences(),
            'adjustmentPeriodicities' => ContractCatalog::adjustmentPeriodicities(),
            'orientationOptions' => ContractCatalog::pageOrientations(),
            'variableDefinitions' => ContractVariableCatalog::definitionsForTemplates(),
        ];
    }

    private function availablePdfAppendixAttachments(Contract $contract)
    {
        $contract->loadMissing(['client', 'condominium.syndic', 'syndic', 'unit.owner', 'unit.tenant']);

        $targets = collect([
            $contract->client ? ['type' => 'entity', 'id' => (int) $contract->client->id, 'label' => 'Cliente vinculado'] : null,
            $contract->syndic ? ['type' => 'entity', 'id' => (int) $contract->syndic->id, 'label' => 'Sindico vinculado'] : null,
            (!$contract->syndic && $contract->condominium?->syndic) ? ['type' => 'entity', 'id' => (int) $contract->condominium->syndic->id, 'label' => 'Sindico do condominio'] : null,
            $contract->condominium ? ['type' => 'condominium', 'id' => (int) $contract->condominium->id, 'label' => 'Condominio vinculado'] : null,
            $contract->unit ? ['type' => 'unit', 'id' => (int) $contract->unit->id, 'label' => 'Unidade vinculada'] : null,
            $contract->unit?->owner ? ['type' => 'entity', 'id' => (int) $contract->unit->owner->id, 'label' => 'Proprietario da unidade'] : null,
            $contract->unit?->tenant ? ['type' => 'entity', 'id' => (int) $contract->unit->tenant->id, 'label' => 'Locatario da unidade'] : null,
        ])->filter();

        if ($targets->isEmpty()) {
            return collect();
        }

        $extensions = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

        return $targets
            ->flatMap(function (array $target) use ($extensions) {
                return ClientAttachment::query()
                    ->where('related_type', $target['type'])
                    ->where('related_id', $target['id'])
                    ->orderByDesc('id')
                    ->get()
                    ->map(function (ClientAttachment $attachment) use ($extensions, $target) {
                        $extension = strtolower((string) pathinfo((string) ($attachment->stored_name ?: $attachment->original_name), PATHINFO_EXTENSION));
                        if (!in_array($extension, $extensions, true)) {
                            return null;
                        }

                        return [
                            'id' => (int) $attachment->id,
                            'original_name' => (string) $attachment->original_name,
                            'stored_name' => (string) $attachment->stored_name,
                            'relative_path' => (string) $attachment->relative_path,
                            'mime_type' => (string) ($attachment->mime_type ?? ''),
                            'file_role' => (string) ($attachment->file_role ?? 'documento'),
                            'owner_label' => $target['label'],
                            'owner_type' => $target['type'],
                            'owner_id' => (int) $target['id'],
                            'extension' => $extension,
                        ];
                    })
                    ->filter();
            })
            ->unique('id')
            ->values();
    }

    private function selectedPdfAppendixAttachments(Contract $contract, array $selectedIds): array
    {
        $ids = collect($selectedIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $available = $this->availablePdfAppendixAttachments($contract)->keyBy('id');

        return $ids
            ->map(function (int $id) use ($available) {
                $row = $available->get($id);
                if ($row) {
                    return $row;
                }

                $attachment = ClientAttachment::query()->find($id);
                if (!$attachment) {
                    return null;
                }

                $extension = strtolower((string) pathinfo((string) ($attachment->stored_name ?: $attachment->original_name), PATHINFO_EXTENSION));

                return [
                    'id' => (int) $attachment->id,
                    'original_name' => (string) $attachment->original_name,
                    'stored_name' => (string) $attachment->stored_name,
                    'relative_path' => (string) $attachment->relative_path,
                    'mime_type' => (string) ($attachment->mime_type ?? ''),
                    'file_role' => (string) ($attachment->file_role ?? 'documento'),
                    'owner_label' => 'Documento selecionado',
                    'owner_type' => (string) $attachment->related_type,
                    'owner_id' => (int) $attachment->related_id,
                    'extension' => $extension,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'scope' => $request->input('scope') === 'trash' ? 'trash' : 'active',
            'q' => trim((string) $request->input('q', '')),
            'client_id' => (int) $request->integer('client_id') ?: null,
            'condominium_id' => (int) $request->integer('condominium_id') ?: null,
            'type' => trim((string) $request->input('type', '')),
            'category_id' => (int) $request->integer('category_id') ?: null,
            'status' => trim((string) $request->input('status', '')),
            'start_from' => trim((string) $request->input('start_from', '')),
            'start_to' => trim((string) $request->input('start_to', '')),
            'end_from' => trim((string) $request->input('end_from', '')),
            'end_to' => trim((string) $request->input('end_to', '')),
            'responsible_user_id' => (int) $request->integer('responsible_user_id') ?: null,
            'expired_only' => $request->boolean('expired_only'),
            'upcoming_only' => $request->boolean('upcoming_only'),
            'without_pdf_only' => $request->boolean('without_pdf_only'),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('contracts.code', 'like', "%{$q}%")
                    ->orWhere('contracts.title', 'like', "%{$q}%")
                    ->orWhere('contracts.type', 'like', "%{$q}%");
            });
        }

        if ($filters['client_id']) {
            $query->where('contracts.client_id', $filters['client_id']);
        }
        if ($filters['condominium_id']) {
            $query->where('contracts.condominium_id', $filters['condominium_id']);
        }
        if ($filters['type'] !== '') {
            $query->where('contracts.type', $filters['type']);
        }
        if ($filters['category_id']) {
            $query->where('contracts.category_id', $filters['category_id']);
        }
        if ($filters['status'] !== '') {
            $query->where('contracts.status', $filters['status']);
        }
        if ($filters['start_from'] !== '') {
            $query->whereDate('contracts.start_date', '>=', $filters['start_from']);
        }
        if ($filters['start_to'] !== '') {
            $query->whereDate('contracts.start_date', '<=', $filters['start_to']);
        }
        if ($filters['end_from'] !== '') {
            $query->whereDate('contracts.end_date', '>=', $filters['end_from']);
        }
        if ($filters['end_to'] !== '') {
            $query->whereDate('contracts.end_date', '<=', $filters['end_to']);
        }
        if ($filters['responsible_user_id']) {
            $query->where('contracts.responsible_user_id', $filters['responsible_user_id']);
        }
        if ($filters['expired_only']) {
            $query->whereNotNull('contracts.end_date')->whereDate('contracts.end_date', '<', now()->toDateString())->where('contracts.indefinite_term', false);
        }
        if ($filters['upcoming_only']) {
            $limit = now()->addDays(max(1, (int) ContractSettings::get('due_alert_days', '30')))->toDateString();
            $query->whereNotNull('contracts.end_date')
                ->whereDate('contracts.end_date', '>=', now()->toDateString())
                ->whereDate('contracts.end_date', '<=', $limit)
                ->where('contracts.indefinite_term', false);
        }
        if ($filters['without_pdf_only']) {
            $query->where(function (Builder $inner) {
                $inner->whereNull('contracts.final_pdf_path')->orWhere('contracts.final_pdf_path', '');
            });
        }
    }

    private function normalizedPayload(Request $request, ?Contract $contract = null): array
    {
        $data = $request->validated();
        $value = function (string $key, mixed $fallback = null) use ($data, $contract) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }

            return $contract?->{$key} ?? $fallback;
        };

        $booleanValue = function (string $key, bool $fallback = false) use ($data, $request, $contract) {
            if ($request->has($key)) {
                return !empty($data[$key]);
            }

            return $contract ? (bool) $contract->{$key} : $fallback;
        };

        $unitId = $value('unit_id');
        $condominiumId = $value('condominium_id');
        $unit = !empty($unitId) ? ClientUnit::query()->with(['owner', 'condominium'])->find((int) $unitId) : null;
        $condominium = !empty($condominiumId) ? ClientCondominium::query()->find((int) $condominiumId) : null;

        if ($unit && empty($condominiumId)) {
            $condominiumId = (int) $unit->condominium_id;
            $condominium = $unit->condominium;
        }
        if ($unit && empty($value('client_id')) && $unit->owner_entity_id) {
            $data['client_id'] = (int) $unit->owner_entity_id;
        }
        if (empty($value('syndico_entity_id')) && $condominium?->syndico_entity_id) {
            $data['syndico_entity_id'] = (int) $condominium->syndico_entity_id;
        }

        return [
            'code' => trim((string) ($value('code') ?? '')) ?: null,
            'title' => trim((string) ($value('title') ?? '')) ?: null,
            'type' => trim((string) $value('type')),
            'category_id' => $value('category_id'),
            'template_id' => $value('template_id'),
            'parent_contract_id' => $value('parent_contract_id'),
            'client_id' => $data['client_id'] ?? $value('client_id'),
            'condominium_id' => $condominiumId,
            'syndico_entity_id' => $data['syndico_entity_id'] ?? $value('syndico_entity_id'),
            'unit_id' => $unitId,
            'proposal_id' => $value('proposal_id'),
            'process_id' => $value('process_id'),
            'status' => trim((string) $value('status')),
            'start_date' => $value('start_date'),
            'end_date' => $value('end_date') ?: null,
            'indefinite_term' => $booleanValue('indefinite_term', false),
            'contract_value' => $this->renderService->moneyFromInput($value('contract_value')),
            'monthly_value' => $this->renderService->moneyFromInput($value('monthly_value')),
            'total_value' => $this->renderService->moneyFromInput($value('total_value')),
            'billing_type' => trim((string) ($value('billing_type') ?? '')) ?: null,
            'installment_quantity' => ($value('installment_quantity') !== null && $value('installment_quantity') !== '')
                ? max(1, (int) $value('installment_quantity'))
                : null,
            'installment_plan' => $this->normalizedInstallmentPlan($value('installment_plan')),
            'due_day' => $value('due_day'),
            'recurrence' => trim((string) ($value('recurrence') ?? '')) ?: null,
            'adjustment_index' => trim((string) ($value('adjustment_index') ?? '')) ?: null,
            'adjustment_periodicity' => trim((string) ($value('adjustment_periodicity') ?? '')) ?: null,
            'next_adjustment_date' => $value('next_adjustment_date'),
            'penalty_value' => $this->renderService->moneyFromInput($value('penalty_value')),
            'penalty_percentage' => $this->renderService->moneyFromInput($value('penalty_percentage')),
            'success_fee_percentage' => $this->renderService->moneyFromInput($value('success_fee_percentage')),
            'generate_financial_entries' => $booleanValue('generate_financial_entries', false),
            'financial_account_id' => $value('financial_account_id'),
            'payment_method' => trim((string) ($value('payment_method') ?? '')) ?: null,
            'cost_center_future' => trim((string) ($value('cost_center_future') ?? '')) ?: null,
            'financial_category_future' => trim((string) ($value('financial_category_future') ?? '')) ?: null,
            'financial_notes' => trim((string) ($value('financial_notes') ?? '')) ?: null,
            'content_html' => $this->normalizedEditorHtml($value('content_html')) ?: null,
            'notes' => trim((string) ($value('notes') ?? '')) ?: null,
            'responsible_user_id' => $value('responsible_user_id'),
        ];
    }

    /**
     * Normaliza o plano de parcelas com valores diferentes vindo do formulario (ou herdado do
     * contrato em edicao). Retorna uma lista [['amount' => float, 'due_date' => 'Y-m-d'|null]] ou
     * null quando nao ha parcelas validas. Linhas sem valor e sem data sao descartadas.
     */
    private function normalizedInstallmentPlan(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $rows = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $amount = $this->renderService->moneyFromInput($entry['amount'] ?? null);
            $dueDate = trim((string) ($entry['due_date'] ?? '')) ?: null;

            if (($amount === null || $amount <= 0) && $dueDate === null) {
                continue;
            }

            $rows[] = [
                'amount' => $amount !== null ? round($amount, 2) : 0.0,
                'due_date' => $dueDate,
            ];
        }

        return $rows === [] ? null : array_values($rows);
    }

    private function applyConditionalContractPayload(array $payload, ?Contract $contract = null): array
    {
        $contractType = $this->normalizedValue($payload['type'] ?? null);
        $typeChanged = $this->valueChanged($contract?->type, $payload['type'] ?? null);
        $indefiniteChanged = $this->boolChanged($contract?->indefinite_term, $payload['indefinite_term'] ?? false);

        if ($indefiniteChanged && !empty($payload['indefinite_term'])) {
            $payload['end_date'] = null;
        }

        if ($contractType === $this->normalizedValue('Contrato de assessoria juridica condominial')) {
            if (empty($payload['billing_type'])) {
                $payload['billing_type'] = 'mensal';
            }
            if (empty($payload['recurrence'])) {
                $payload['recurrence'] = 'mensal';
            }
        }

        if ($typeChanged) {
            if (in_array($contractType, [
                $this->normalizedValue('Termo de acordo'),
                $this->normalizedValue('Confissao de divida'),
                $this->normalizedValue('Distrato'),
            ], true)) {
                $payload['adjustment_index'] = null;
                $payload['adjustment_periodicity'] = null;
                $payload['next_adjustment_date'] = null;
            }

            if ($contractType === $this->normalizedValue('Distrato')) {
                $payload['generate_financial_entries'] = false;
                $payload['recurrence'] = null;
            }
        }

        $billingType = $this->normalizedValue($payload['billing_type'] ?? null);
        $paymentMethod = $this->normalizedValue($payload['payment_method'] ?? null);
        $status = $this->normalizedValue($payload['status'] ?? null);
        $billingChanged = $this->valueChanged($contract?->billing_type, $payload['billing_type'] ?? null);
        $paymentChanged = $this->valueChanged($contract?->payment_method, $payload['payment_method'] ?? null);

        if ($billingType === 'unica') {
            $payload['installment_quantity'] = 1;
        }

        if ($billingType === 'mensal' && empty($payload['recurrence'])) {
            $payload['recurrence'] = 'mensal';
        }

        if ($billingChanged) {
            if ($billingType === 'unica') {
                $payload['monthly_value'] = null;
                $payload['recurrence'] = null;
                $payload['adjustment_index'] = null;
                $payload['adjustment_periodicity'] = null;
                $payload['next_adjustment_date'] = null;
            } elseif ($billingType === 'parcelada') {
                $payload['monthly_value'] = null;
                $payload['recurrence'] = null;
                $payload['adjustment_index'] = null;
                $payload['adjustment_periodicity'] = null;
                $payload['next_adjustment_date'] = null;
            } elseif ($billingType === 'mensal') {
                $payload['installment_quantity'] = null;
                if (!empty($payload['indefinite_term'])) {
                    $payload['total_value'] = null;
                }
            } elseif (in_array($billingType, ['honorarios_sobre_exito', 'sob_demanda'], true)) {
                $payload['monthly_value'] = null;
                $payload['due_day'] = null;
                $payload['recurrence'] = null;
                $payload['adjustment_index'] = null;
                $payload['adjustment_periodicity'] = null;
                $payload['next_adjustment_date'] = null;
                $payload['installment_quantity'] = null;
                $payload['generate_financial_entries'] = false;
            }
        }

        if ($paymentChanged && in_array($paymentMethod, ['especie', 'dinheiro'], true)) {
            $payload['financial_account_id'] = null;
        }

        if ($contractType === $this->normalizedValue('Contrato de assessoria juridica condominial') && $billingType === 'mensal') {
            $payload['installment_quantity'] = null;
        }

        if (in_array($status, ['rescindido', 'cancelado', 'arquivado'], true)) {
            $payload['generate_financial_entries'] = false;
        }

        if (in_array($billingType, ['honorarios_sobre_exito', 'sob_demanda'], true)) {
            $payload['generate_financial_entries'] = false;
        }

        if ($contractType === $this->normalizedValue('Distrato')) {
            $payload['recurrence'] = null;
        }

        // O plano de parcelas com valores diferentes so faz sentido para cobranca parcelada.
        // Em qualquer outra forma, descarta o plano; quando parcelada com plano informado, a
        // quantidade de parcelas passa a refletir o numero de linhas do plano.
        if ($billingType !== 'parcelada') {
            $payload['installment_plan'] = null;
        } elseif (!empty($payload['installment_plan']) && is_array($payload['installment_plan'])) {
            $payload['installment_quantity'] = max(1, count($payload['installment_plan']));
        }

        return $payload;
    }

    private function contractSaveGuardResponse(Request $request, array $payload, ?Contract $contract = null): ?RedirectResponse
    {
        $errors = $this->contractBaseValidationErrors($payload);
        if ($this->shouldEnforceStrictFinancialValidation($payload)) {
            $errors = array_merge($errors, $this->contractFinancialService->validateFinancialData($payload));
        }

        if ($errors !== []) {
            return back()
                ->withInput()
                ->with('error', 'Nao foi possivel salvar o contrato com as regras financeiras atuais. Revise os campos sinalizados abaixo.')
                ->with('errors_list', array_values(array_unique($errors)));
        }

        if ($this->needsActiveWithoutFinancialConfirmation($payload) && !$request->boolean('confirm_active_without_financial')) {
            return back()
                ->withInput()
                ->with('error', 'Confirme que deseja salvar este contrato como ativo/assinado sem gerar lancamentos automaticos no Financeiro 360.');
        }

        if ($this->needsExistingEntriesDecision($payload, $contract) && !in_array((string) $request->input('financial_entries_action', ''), ['maintain', 'recreate', 'refresh_open_future'], true)) {
            return back()
                ->withInput()
                ->with('error', 'Escolha se deseja manter os lancamentos financeiros atuais ou recria-los antes de concluir a alteracao do contrato.');
        }

        return null;
    }

    private function contractBaseValidationErrors(array $payload): array
    {
        $errors = [];
        $billingType = $this->normalizedValue($payload['billing_type'] ?? null);
        $paymentMethod = $this->normalizedValue($payload['payment_method'] ?? null);
        $installmentQuantity = (int) ($payload['installment_quantity'] ?? 0);
        $penaltyValue = (float) ($payload['penalty_value'] ?? 0);
        $penaltyPercentage = (float) ($payload['penalty_percentage'] ?? 0);

        if ($penaltyValue > 0 && $penaltyPercentage > 0) {
            $errors[] = 'Informe a multa em valor ou em percentual, nunca as duas ao mesmo tempo.';
        }

        if (!empty($payload['adjustment_index']) && empty($payload['adjustment_periodicity'])) {
            $errors[] = 'Ao informar o indice de reajuste, a periodicidade de reajuste passa a ser obrigatoria.';
        }

        if ($billingType === 'parcelada' && $installmentQuantity < 2) {
            $errors[] = 'A quantidade de parcelas deve ser de no minimo 2 quando a forma de cobranca for Parcelado.';
        }

        $plan = $payload['installment_plan'] ?? null;
        if ($billingType === 'parcelada' && is_array($plan) && $plan !== []) {
            $sum = 0.0;
            $hasInvalidAmount = false;
            foreach ($plan as $row) {
                $amount = (float) ($row['amount'] ?? 0);
                if ($amount <= 0) {
                    $hasInvalidAmount = true;
                }
                $sum += $amount;
            }

            if ($hasInvalidAmount) {
                $errors[] = 'Cada parcela personalizada deve ter um valor maior que zero.';
            }

            $total = (float) ($payload['total_value'] ?? 0);
            if ($total > 0 && abs($sum - $total) > 0.05) {
                $errors[] = sprintf(
                    'A soma das parcelas (R$ %s) deve ser igual ao valor total do contrato (R$ %s).',
                    number_format($sum, 2, ',', '.'),
                    number_format($total, 2, ',', '.')
                );
            }
        }

        if ($billingType === 'unica' && $installmentQuantity > 1) {
            $errors[] = 'A quantidade de parcelas deve permanecer igual a 1 quando a forma de cobranca for Parcela unica.';
        }

        if ($paymentMethod === 'boleto' && empty($payload['due_day'])) {
            $errors[] = 'Ao selecionar Boleto, o dia de vencimento passa a ser obrigatorio.';
        }

        if ($paymentMethod === 'boleto' && $billingType === 'mensal' && empty($payload['recurrence'])) {
            $errors[] = 'Contratos mensais com pagamento em boleto exigem recorrencia definida.';
        }

        if ($paymentMethod === 'cartao' && $billingType === 'parcelada' && $installmentQuantity < 2) {
            $errors[] = 'Pagamentos em cartao parcelado exigem a quantidade de parcelas com valor minimo de 2.';
        }

        if ($paymentMethod === 'cheque' && !empty($payload['generate_financial_entries']) && $billingType === 'mensal') {
            $errors[] = 'No fluxo atual, contratos mensais pagos por cheque nao podem gerar cobrancas automaticas recorrentes.';
        }

        return $errors;
    }

    private function shouldEnforceStrictFinancialValidation(array $payload): bool
    {
        return !empty($payload['generate_financial_entries'])
            && in_array($this->normalizedValue($payload['status'] ?? null), ['ativo', 'assinado'], true);
    }

    private function needsActiveWithoutFinancialConfirmation(array $payload): bool
    {
        return in_array($this->normalizedValue($payload['status'] ?? null), ['ativo', 'assinado'], true)
            && empty($payload['generate_financial_entries']);
    }

    private function needsExistingEntriesDecision(array $payload, ?Contract $contract = null): bool
    {
        if (!$contract) {
            return false;
        }

        return !$contract->generate_financial_entries
            && !empty($payload['generate_financial_entries'])
            && in_array($this->normalizedValue($payload['status'] ?? null), ['ativo', 'assinado'], true)
            && $this->contractFinancialService->hasFinancialEntries($contract);
    }

    private function syncContractFinancialEntriesAfterSave(Contract $contract, ?Contract $originalContract, int $userId, string $action): array
    {
        $result = ['created' => 0, 'skipped' => 0, 'deleted' => 0, 'mode' => null];

        if (!$contract->generate_financial_entries || !in_array($contract->status, ['ativo', 'assinado'], true)) {
            return $result;
        }

        if ($action === 'maintain' && $this->contractFinancialService->hasFinancialEntries($contract)) {
            $result['mode'] = 'maintain';
            return $result;
        }

        if ($action === 'recreate') {
            $sync = $this->contractFinancialService->recreateFinancialEntries($contract, $userId);

            return [
                'created' => $sync['created']->count(),
                'skipped' => $sync['skipped']->count(),
                'deleted' => (int) ($sync['deleted'] ?? 0),
                'mode' => 'recreate',
            ];
        }

        if ($action === 'refresh_open_future') {
            $sync = $this->contractFinancialService->refreshOpenAndFutureFinancialEntries($contract, $userId);

            return [
                'created' => $sync['created']->count(),
                'skipped' => $sync['skipped']->count(),
                'deleted' => (int) ($sync['deleted'] ?? 0),
                'protected' => (int) (($sync['protected'] ?? collect())->count()),
                'mode' => 'refresh_open_future',
            ];
        }

        if ($this->contractFinancialService->hasFinancialEntries($contract)) {
            $result['mode'] = 'existing';
            return $result;
        }

        $sync = $this->contractFinancialService->generateFinancialEntries($contract, $userId);

        return [
            'created' => $sync['created']->count(),
            'skipped' => $sync['skipped']->count(),
            'deleted' => (int) ($sync['deleted'] ?? 0),
            'mode' => 'generate',
        ];
    }

    private function contractFinancialSyncMessage(array $financialSync): string
    {
        $created = (int) ($financialSync['created'] ?? 0);
        $skipped = (int) ($financialSync['skipped'] ?? 0);
        $deleted = (int) ($financialSync['deleted'] ?? 0);
        $mode = (string) ($financialSync['mode'] ?? '');

        if ($mode === 'maintain') {
            return ' Os lancamentos financeiros existentes foram mantidos sem duplicacao.';
        }

        if ($mode === 'existing') {
            return ' O contrato ja possuia lancamentos financeiros vinculados, entao nenhuma duplicidade foi gerada.';
        }

        if ($mode === 'recreate') {
            return ' Financeiro 360 atualizado: ' . $created . ' lancamento(s) criado(s), ' . $deleted . ' registro(s) anterior(es) removido(s) e ' . $skipped . ' item(ns) pulado(s).';
        }

        if ($mode === 'refresh_open_future') {
            $protected = (int) ($financialSync['protected'] ?? 0);

            return ' Financeiro 360 atualizado a partir do mes atual: ' . $created . ' lancamento(s) criado(s), ' . $deleted . ' aberto(s) substituido(s), ' . $skipped . ' mantido(s) por duplicidade e ' . $protected . ' protegido(s) por baixa/movimentacao.';
        }

        if ($mode === 'generate' && ($created > 0 || $skipped > 0)) {
            return ' Financeiro 360: ' . $created . ' lancamento(s) criado(s) e ' . $skipped . ' pulado(s) por duplicidade.';
        }

        return '';
    }

    private function valueChanged(mixed $original, mixed $current): bool
    {
        return $this->normalizedValue($original) !== $this->normalizedValue($current);
    }

    private function boolChanged(mixed $original, mixed $current): bool
    {
        return (bool) $original !== (bool) $current;
    }

    private function normalizedValue(mixed $value): string
    {
        return Str::of(Str::ascii(trim((string) $value)))->lower()->squish()->toString();
    }

    private function normalizedEditorHtml(mixed $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $plain = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $html);
        $plain = preg_replace('/<br\s*\/?>/i', ' ', $plain) ?? $plain;
        $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return trim($plain) === '' ? '' : $html;
    }

    private function resolvedTitle(?ContractTemplate $template, array $payload, ?Contract $contract = null): ?string
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $templateTitle = trim((string) ($template?->default_contract_title ?: $template?->name ?: ''));
        if ($templateTitle !== '') {
            return $templateTitle;
        }

        return trim((string) ($contract?->title ?? '')) ?: null;
    }

    private function contractAlerts(Contract $contract): array
    {
        $alertDays = max(1, (int) ContractSettings::get('due_alert_days', '30'));
        $today = now()->startOfDay();
        $limit = now()->copy()->addDays($alertDays)->endOfDay();
        $alerts = [];

        if (!$contract->indefinite_term && $contract->end_date && $contract->end_date->between($today, $limit)) {
            $alerts[] = [
                'type' => 'warning',
                'label' => 'Vencimento proximo',
                'message' => 'Este contrato vence em ' . $contract->end_date->format('d/m/Y') . '.',
            ];
        }

        if ($contract->next_adjustment_date && $contract->next_adjustment_date->between($today, $limit)) {
            $alerts[] = [
                'type' => 'info',
                'label' => 'Reajuste proximo',
                'message' => 'O proximo reajuste esta previsto para ' . $contract->next_adjustment_date->format('d/m/Y') . '.',
            ];
        }

        return $alerts;
    }

    private function autoCodeEnabled(): bool
    {
        return ContractSettings::bool('auto_code', true);
    }

    private function generatedCode(Contract $contract): string
    {
        $prefix = strtoupper(trim((string) ContractSettings::get('code_prefix', 'CTR')) ?: 'CTR');
        $year = (int) ($contract->created_at?->year ?: now()->year);

        // Comeca pelo id do contrato, mas garante unicidade incrementando
        // a sequencia caso o codigo ja exista (inclui registros na lixeira,
        // codigos informados manualmente e ids reaproveitados).
        $sequence = (int) $contract->id;

        do {
            $code = $prefix . '-' . $year . '-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            $exists = Contract::withTrashed()
                ->where('code', $code)
                ->whereKeyNot($contract->id)
                ->exists();
            $sequence++;
        } while ($exists);

        return $code;
    }

    private function isExpired(Contract $contract): bool
    {
        return !$contract->indefinite_term && $contract->end_date && $contract->end_date->isPast();
    }

    private function isUpcoming(Contract $contract, int $days): bool
    {
        if ($contract->indefinite_term || !$contract->end_date) {
            return false;
        }

        return $contract->end_date->between(now()->startOfDay(), now()->copy()->addDays($days)->endOfDay());
    }

    private function isAdjustmentUpcoming(Contract $contract, int $days): bool
    {
        if (!$contract->next_adjustment_date) {
            return false;
        }

        return $contract->next_adjustment_date->between(now()->startOfDay(), now()->copy()->addDays($days)->endOfDay());
    }
}
