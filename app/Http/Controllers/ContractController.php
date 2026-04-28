<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractCategory;
use App\Models\ContractTemplate;
use App\Models\ContractVersion;
use App\Models\ProcessCase;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ContractPdfService;
use App\Services\ContractRenderService;
use App\Support\AncoraAuth;
use App\Support\ContractSettings;
use App\Support\Contracts\ContractCatalog;
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
    ) {
    }

    public function dashboard(Request $request): View
    {
        $year = max(2024, (int) $request->integer('year', now()->year));
        $monthLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $alertDays = max(1, (int) ContractSettings::get('due_alert_days', '30'));
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $contracts = Contract::query()
            ->with(['category', 'client', 'condominium', 'responsible'])
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
            'month_label' => $monthLabels[now()->month - 1] . '/' . now()->year,
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
            'without_pdf' => $contracts->filter(fn (Contract $item) => !$item->final_pdf_path)->take(8)->values(),
            'drafts' => $contracts->where('status', 'rascunho')->take(8)->values(),
            'without_client' => $contracts->filter(fn (Contract $item) => !$item->client_id && !$item->condominium_id)->take(8)->values(),
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
            ->with(['category', 'client', 'condominium', 'responsible'])
            ->select('contracts.*');

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
        ];

        return view('pages.contratos.form', array_merge($this->formOptions(), [
            'title' => 'Novo contrato',
            'mode' => 'create',
            'item' => null,
            'draft' => $draft,
            'previewHtml' => old('content_html', ''),
        ]));
    }

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $template = $request->filled('template_id')
            ? ContractTemplate::query()->find((int) $request->input('template_id'))
            : null;
        $payload = $this->normalizedPayload($request);

        if (!$this->autoCodeEnabled() && trim((string) $payload['code']) === '') {
            return back()->withInput()->with('error', 'Informe o código interno do contrato quando a numeração automática estiver desativada.');
        }

        if ($payload['code'] && Contract::query()->where('code', $payload['code'])->exists()) {
            return back()->withInput()->with('error', 'Já existe um contrato com esse código interno.');
        }

        if (trim((string) $payload['content_html']) === '' && $template) {
            $payload['content_html'] = $this->renderService->renderTemplate($template, array_merge($request->all(), $payload));
        }

        if ($request->boolean('generate_pdf_now') && !$template) {
            return back()->withInput()->with('error', 'Selecione um template antes de gerar o PDF do contrato.');
        }

        try {
            $contract = DB::transaction(function () use ($payload, $user, $request) {
                $contract = Contract::query()->create(array_merge($payload, [
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]));

                if ($this->autoCodeEnabled() && !$contract->code) {
                    $contract->forceFill(['code' => $this->generatedCode($contract)])->save();
                }

                if ($request->boolean('generate_pdf_now')) {
                    $this->pdfService->generate($contract->fresh(['template', 'client', 'condominium', 'unit', 'responsible']), $user->id, (string) $request->input('version_notes', 'Versão inicial.'));
                }

                return $contract;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Não foi possível salvar o contrato: ' . $e->getMessage());
        }

        return redirect()
            ->route('contratos.show', $contract)
            ->with('success', $request->boolean('generate_pdf_now') ? 'Contrato salvo e PDF gerado com sucesso.' : 'Contrato salvo com sucesso.');
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
            'unit.block',
            'proposal',
            'process',
            'responsible',
            'creator',
            'updater',
            'versions.generator',
            'attachments.uploader',
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
            'signatureStorageReady' => $signatureStorageReady,
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
        $contrato->load(['category', 'template', 'client', 'condominium', 'unit', 'responsible']);

        return view('pages.contratos.form', array_merge($this->formOptions(), [
            'title' => 'Editar contrato',
            'mode' => 'edit',
            'item' => $contrato,
            'draft' => $contrato->toArray(),
            'previewHtml' => old('content_html', $contrato->content_html),
        ]));
    }

    public function update(UpdateContractRequest $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $template = $request->filled('template_id')
            ? ContractTemplate::query()->find((int) $request->input('template_id'))
            : null;
        $payload = $this->normalizedPayload($request);

        if (!$this->autoCodeEnabled() && trim((string) ($payload['code'] ?? '')) === '') {
            return back()->withInput()->with('error', 'Informe o código interno do contrato quando a numeração automática estiver desativada.');
        }

        if (!empty($payload['code']) && Contract::query()->where('code', $payload['code'])->whereKeyNot($contrato->id)->exists()) {
            return back()->withInput()->with('error', 'Já existe outro contrato com esse código interno.');
        }

        if (trim((string) $payload['content_html']) === '' && $template) {
            $payload['content_html'] = $this->renderService->renderTemplate($template, array_merge($request->all(), $payload));
        }

        if ($request->boolean('generate_pdf_now') && !$template) {
            return back()->withInput()->with('error', 'Selecione um template antes de gerar o PDF do contrato.');
        }

        try {
            DB::transaction(function () use ($contrato, $payload, $user, $request) {
                $contrato->update(array_merge($payload, ['updated_by' => $user->id]));

                if ($request->boolean('generate_pdf_now')) {
                    $this->pdfService->generate($contrato->fresh(['template', 'client', 'condominium', 'unit', 'responsible']), $user->id, (string) $request->input('version_notes', 'Nova versão gerada pela edição do contrato.'));
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Não foi possível atualizar o contrato: ' . $e->getMessage());
        }

        return redirect()
            ->route('contratos.show', $contrato)
            ->with('success', $request->boolean('generate_pdf_now') ? 'Contrato atualizado e nova versão de PDF gerada.' : 'Contrato atualizado com sucesso.');
    }

    public function destroy(Contract $contrato): RedirectResponse
    {
        $contrato->delete();

        return redirect()->route('contratos.index')->with('success', 'Contrato excluído com sucesso.');
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
            $clone->title = $contrato->title . ' (cópia)';
            $clone->status = ContractSettings::get('default_status', 'rascunho');
            $clone->code = null;
            $clone->created_by = $user->id;
            $clone->updated_by = $user->id;
            $clone->save();

            if ($this->autoCodeEnabled()) {
                $clone->forceFill(['code' => $this->generatedCode($clone)])->save();
            }
        });

        return redirect()->route('contratos.edit', $clone)->with('success', 'Contrato duplicado. Revise os dados antes de salvar a nova versão.');
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

    public function resolvePreview(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'content_html' => ['nullable', 'string'],
        ]);

        $template = $request->filled('template_id')
            ? ContractTemplate::query()->find((int) $request->input('template_id'))
            : null;

        $html = trim((string) $request->input('content_html'));
        if ($html === '' && !$template) {
            return response()->json(['message' => 'Selecione um template para carregar o preview.'], 422);
        }

        return response()->json([
            'html' => $this->renderService->renderTemplate($template, $request->all(), $html !== '' ? $html : null),
        ]);
    }

    public function generatePdf(Request $request, Contract $contrato): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        if (!$contrato->template_id) {
            return redirect()->route('contratos.show', $contrato)->with('error', 'Selecione um template no contrato antes de gerar o PDF.');
        }

        if (trim((string) $contrato->content_html) === '') {
            return redirect()->route('contratos.edit', $contrato)->with('error', 'O contrato ainda não possui conteúdo editável salvo.');
        }

        try {
            $version = $this->pdfService->generate($contrato, $user->id, trim((string) $request->input('version_notes', 'Versão gerada manualmente.')) ?: 'Versão gerada manualmente.');
        } catch (\Throwable $e) {
            return redirect()->route('contratos.show', $contrato)->with('error', 'Não foi possível gerar o PDF: ' . $e->getMessage());
        }

        return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'historico'])->with('success', 'PDF gerado com sucesso na versão v' . $version->version_number . '.');
    }

    public function downloadPdf(Contract $contrato): BinaryFileResponse|RedirectResponse
    {
        $path = $this->pdfService->absolutePath($contrato->final_pdf_path);
        if (!$path) {
            return redirect()->route('contratos.show', $contrato)->with('error', 'Este contrato ainda não possui PDF final gerado.');
        }

        return response()->download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }

    public function viewVersion(Contract $contrato, ContractVersion $version): BinaryFileResponse|RedirectResponse
    {
        abort_if((int) $version->contract_id !== (int) $contrato->id, 404);

        $path = $this->pdfService->absolutePath($version->pdf_path);
        if (!$path) {
            return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'historico'])->with('error', 'O arquivo PDF desta versão não foi encontrado.');
        }

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }

    public function downloadVersion(Contract $contrato, ContractVersion $version): BinaryFileResponse|RedirectResponse
    {
        abort_if((int) $version->contract_id !== (int) $contrato->id, 404);

        $path = $this->pdfService->absolutePath($version->pdf_path);
        if (!$path) {
            return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'historico'])->with('error', 'O arquivo PDF desta versão não foi encontrado.');
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

        return redirect()->route('contratos.show', ['contrato' => $contrato, 'tab' => 'anexos'])->with('success', 'Anexo excluído com sucesso.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => ContractCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'templates' => ContractTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'clients' => ClientEntity::query()->where('is_active', true)->orderBy('display_name')->get(['id', 'display_name']),
            'condominiums' => ClientCondominium::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => ClientUnit::query()->with(['condominium', 'block'])->orderBy('unit_number')->get(),
            'proposals' => Proposal::query()->orderByDesc('id')->limit(300)->get(['id', 'proposal_code', 'client_name']),
            'processes' => class_exists(ProcessCase::class)
                ? ProcessCase::query()->orderByDesc('id')->limit(300)->get(['id', 'process_number', 'client_name_snapshot'])
                : collect(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statusLabels' => ContractCatalog::statuses(),
            'typeOptions' => ContractCatalog::types(),
            'billingTypes' => ContractCatalog::billingTypes(),
            'recurrenceOptions' => ContractCatalog::recurrences(),
            'adjustmentPeriodicities' => ContractCatalog::adjustmentPeriodicities(),
            'orientationOptions' => ContractCatalog::pageOrientations(),
        ];
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
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

    private function normalizedPayload(Request $request): array
    {
        $data = $request->validated();
        $unit = !empty($data['unit_id']) ? ClientUnit::query()->with('owner')->find((int) $data['unit_id']) : null;

        if ($unit && empty($data['condominium_id'])) {
            $data['condominium_id'] = (int) $unit->condominium_id;
        }
        if ($unit && empty($data['client_id']) && $unit->owner_entity_id) {
            $data['client_id'] = (int) $unit->owner_entity_id;
        }

        return [
            'code' => trim((string) ($data['code'] ?? '')) ?: null,
            'title' => trim((string) $data['title']),
            'type' => trim((string) $data['type']),
            'category_id' => $data['category_id'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'condominium_id' => $data['condominium_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'proposal_id' => $data['proposal_id'] ?? null,
            'process_id' => $data['process_id'] ?? null,
            'status' => trim((string) $data['status']),
            'start_date' => $data['start_date'] ?? null,
            'end_date' => !empty($data['indefinite_term']) ? null : ($data['end_date'] ?? null),
            'indefinite_term' => !empty($data['indefinite_term']),
            'contract_value' => $this->renderService->moneyFromInput($data['contract_value'] ?? null),
            'monthly_value' => $this->renderService->moneyFromInput($data['monthly_value'] ?? null),
            'total_value' => $this->renderService->moneyFromInput($data['total_value'] ?? null),
            'billing_type' => trim((string) ($data['billing_type'] ?? '')) ?: null,
            'due_day' => $data['due_day'] ?? null,
            'recurrence' => trim((string) ($data['recurrence'] ?? '')) ?: null,
            'adjustment_index' => trim((string) ($data['adjustment_index'] ?? '')) ?: null,
            'adjustment_periodicity' => trim((string) ($data['adjustment_periodicity'] ?? '')) ?: null,
            'next_adjustment_date' => $data['next_adjustment_date'] ?? null,
            'penalty_value' => $this->renderService->moneyFromInput($data['penalty_value'] ?? null),
            'penalty_percentage' => $this->renderService->moneyFromInput($data['penalty_percentage'] ?? null),
            'generate_financial_entries' => !empty($data['generate_financial_entries']),
            'cost_center_future' => trim((string) ($data['cost_center_future'] ?? '')) ?: null,
            'financial_category_future' => trim((string) ($data['financial_category_future'] ?? '')) ?: null,
            'financial_notes' => trim((string) ($data['financial_notes'] ?? '')) ?: null,
            'content_html' => trim((string) ($data['content_html'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
        ];
    }

    private function autoCodeEnabled(): bool
    {
        return ContractSettings::bool('auto_code', true);
    }

    private function generatedCode(Contract $contract): string
    {
        $prefix = trim((string) ContractSettings::get('code_prefix', 'CTR')) ?: 'CTR';
        $year = (int) ($contract->created_at?->year ?: now()->year);

        return strtoupper($prefix) . '-' . $year . '-' . str_pad((string) $contract->id, 5, '0', STR_PAD_LEFT);
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
}
