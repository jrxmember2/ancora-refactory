<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseAttachment;
use App\Models\CobrancaCaseContact;
use App\Models\CobrancaCaseInstallment;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaCaseTimeline;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CobrancaController extends Controller
{
    public function dashboard(Request $request): View
    {
        $year = max(2024, (int) $request->integer('year', now()->year));
        $base = CobrancaCase::query()->where('charge_year', $year);

        $summary = [
            'total' => (clone $base)->count(),
            'notificar' => (clone $base)->where('workflow_stage', 'apto_notificar')->count(),
            'negociacao' => (clone $base)->where('workflow_stage', 'em_negociacao')->count(),
            'aguardando_assinatura' => (clone $base)->where('workflow_stage', 'aguardando_assinatura')->count(),
            'acordo_ativo' => (clone $base)->where('workflow_stage', 'acordo_ativo')->count(),
            'judicializar' => (clone $base)->where('workflow_stage', 'apto_judicializar')->count(),
            'ajuizado' => (clone $base)->where('situation', 'ajuizado')->count(),
            'encerrado' => (clone $base)->where('situation', 'pago_encerrado')->count(),
            'agreement_total' => (float) (clone $base)->sum('agreement_total'),
            'entry_total' => (float) (clone $base)->sum('entry_amount'),
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
        ]);
    }

    public function index(Request $request): View
    {
        $query = CobrancaCase::query()
            ->with(['condominium', 'block', 'unit'])
            ->withCount(['contacts', 'quotas', 'attachments'])
            ->orderByDesc('id');

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
            $query->where('condominium_id', (int) $request->integer('condominium_id'));
        }
        foreach (['charge_type', 'situation', 'workflow_stage', 'billing_status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (string) $request->input($filter));
            }
        }
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->input('date_from'));
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->input('date_to'));

        $items = $query->paginate(max(10, min(100, (int) $request->integer('per_page', 15))))->withQueryString();

        return view('pages.cobrancas.index', [
            'title' => 'Cobranças',
            'items' => $items,
            'filters' => $request->all(),
            'filterOptions' => $this->filterOptions(),
        ]);
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
        $cobranca->load([
            'condominium', 'block', 'unit.owner', 'unit.tenant', 'contacts', 'quotas', 'installments', 'timeline', 'attachments',
        ]);

        return view('pages.cobrancas.show', [
            'title' => 'OS ' . $cobranca->os_number,
            'case' => $cobranca,
            'n8nPayload' => $this->n8nPayload($cobranca),
            'stageLabels' => $this->workflowStageLabels(),
            'situationLabels' => $this->situationLabels(),
            'billingLabels' => $this->billingStatusLabels(),
            'entryStatusLabels' => $this->entryStatusLabels(),
        ]);
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
            'situation' => trim((string) $request->input('situation', 'processo_aberto')) ?: 'processo_aberto',
            'workflow_stage' => trim((string) $request->input('workflow_stage', 'triagem')) ?: 'triagem',
            'entry_status' => trim((string) $request->input('entry_status', '')) ?: null,
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
            $errors[] = 'Selecione uma etapa válida.';
        }
        if (!in_array($payload['situation'], array_keys($this->situationLabels()), true)) {
            $errors[] = 'Selecione uma situação válida.';
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
        if ($payload['entry_amount'] !== null && $payload['entry_due_date'] === null) {
            $errors[] = 'Informe o vencimento da entrada.';
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
            'workflow_stage' => old('workflow_stage', $case?->workflow_stage ?: 'triagem'),
            'entry_status' => old('entry_status', $case?->entry_status),
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
                if ($dueDate === '' && $amount === null && trim((string) ($row['label'] ?? '')) === '') {
                    return null;
                }
                return [
                    'label' => Str::limit(trim((string) ($row['label'] ?? '')), 100, ''),
                    'installment_type' => trim((string) ($row['installment_type'] ?? 'parcela')) ?: 'parcela',
                    'installment_number' => (int) ($row['installment_number'] ?? 0),
                    'due_date' => $dueDate ?: now()->toDateString(),
                    'amount' => $amount ?? 0,
                    'status' => trim((string) ($row['status'] ?? 'pendente')) ?: 'pendente',
                ];
            })
            ->filter()
            ->values()
            ->all();
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
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) >= 6) {
            return substr($digits, 0, 2) . '/' . substr($digits, 2, 4);
        }

        return trim($value);
    }

    private function normalizeQuotaKind(?string $value): string
    {
        return match ((string) $value) {
            'taxa_extra' => 'taxa_extra',
            'parcela_acordo' => 'parcela_acordo',
            default => 'taxa_mes',
        };
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
            'notificado' => 'Notificado',
            'sem_retorno' => 'Sem retorno',
            'em_negociacao' => 'Em negociação',
            'aguardando_termo' => 'Aguardando termo',
            'aguardando_assinatura' => 'Aguardando assinatura',
            'aguardando_boletos' => 'Aguardando boletos da administradora',
            'acordo_ativo' => 'Acordo ativo',
            'acordo_inadimplido' => 'Acordo inadimplido',
            'apto_judicializar' => 'Apto para judicializar',
            'judicializado' => 'Judicializado',
            'encerrado' => 'Encerrado',
        ];
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
