<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\AuditLog;
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
use App\Models\CobrancaMonetaryUpdate;
use App\Models\CobrancaMonetaryUpdateItem;
use App\Models\User;
use App\Services\CobrancaMonetaryUpdateService;
use App\Support\AncoraBillingMail;
use App\Support\Hub\HubModulePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CollectionController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = CobrancaCase::query()
            ->with(['condominium', 'unit.owner', 'unit.tenant', 'debtor']);

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('os_number', 'like', "%{$term}%")
                    ->orWhere('debtor_name_snapshot', 'like', "%{$term}%")
                    ->orWhere('debtor_document_snapshot', 'like', "%{$term}%")
                    ->orWhere('judicial_case_number', 'like', "%{$term}%")
                    ->orWhereHas('condominium', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('unit', fn (Builder $query) => $query->where('unit_number', 'like', "%{$term}%")->orWhere('unit_label', 'like', "%{$term}%"))
                    ->orWhereHas('unit.owner', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"))
                    ->orWhereHas('unit.tenant', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"));
            });
        }

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where(function (Builder $inner) use ($status) {
                $inner->where('workflow_stage', $status)
                    ->orWhere('situation', $status)
                    ->orWhere('billing_status', $status);
            });
        }

        if ($workflowStage = trim((string) $request->query('workflow_stage', ''))) {
            $query->where('workflow_stage', $workflowStage);
        }

        if ($situation = trim((string) $request->query('situation', ''))) {
            $query->where('situation', $situation);
        }

        if ($billingStatus = trim((string) $request->query('billing_status', ''))) {
            $query->where('billing_status', $billingStatus);
        }

        $items = $query
            ->latest('updated_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCase $case) => HubModulePresenter::collectionSummary($case))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'workflow_stages' => collect(HubModulePresenter::collectionWorkflowStageLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'situations' => collect(HubModulePresenter::collectionSituationLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'billing_statuses' => collect(HubModulePresenter::collectionBillingStatusLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
            ],
            'actions' => [
                'can_create' => $this->userCanAnyRoute($user, ['cobrancas.create', 'cobrancas.store']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.create', 'cobrancas.store'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $this->validateCollectionRequest($request, creating: true);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $unit = $this->findCollectionUnit((int) $validated['unit_id']);
        if (!$unit) {
            return response()->json([
                'ok' => false,
                'message' => 'A unidade informada não foi encontrada.',
            ], 422);
        }

        if (!$unit->owner) {
            return response()->json([
                'ok' => false,
                'message' => 'A unidade selecionada precisa ter um proprietário vinculado para gerar a OS.',
            ], 422);
        }

        try {
            $case = DB::transaction(function () use ($validated, $unit, $user, $request) {
                $payload = $this->buildCollectionPayload($validated, $unit, $user);
                $payload['charge_year'] = $this->nextChargeYear($validated['calc_base_date'] ?? null);
                $payload['charge_seq'] = $this->nextChargeSequence($payload['charge_year']);
                $payload['os_number'] = sprintf('COB-%d-%05d', $payload['charge_year'], $payload['charge_seq']);
                $payload['created_by'] = $user->id;
                $payload['updated_by'] = $user->id;
                $payload['last_progress_at'] = now();

                /** @var CobrancaCase $case */
                $case = CobrancaCase::query()->create($payload);
                $this->syncCaseQuotas($case, $validated['quotas']);
                $this->syncCaseContactsFromUnit($case, $unit);
                $this->recordTimeline($case, 'system', 'OS criada pelo aplicativo Âncora Hub.', $user, $request, now());
                $this->logAction($request, 'create_cobranca_case_hub', $case->id, 'Cadastro de nova OS de cobrança pelo aplicativo - ' . $case->os_number, $user);

                return $case;
            });
        } catch (\Throwable) {
            return $this->serverErrorResponse('Não foi possível criar a OS agora. Tente novamente.');
        }

        return response()->json([
            'ok' => true,
            'message' => 'OS criada com sucesso.',
            'item' => $this->presentCollectionDetail($case->fresh(), $user),
        ], 201);
    }

    public function show(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json([
            'item' => $this->presentCollectionDetail($collection, $user),
        ]);
    }

    public function update(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.edit', 'cobrancas.update'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $this->validateCollectionRequest($request, creating: false);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $unitId = isset($validated['unit_id']) ? (int) $validated['unit_id'] : (int) $collection->unit_id;
        $unit = $this->findCollectionUnit($unitId);
        if (!$unit) {
            return response()->json([
                'ok' => false,
                'message' => 'A unidade informada não foi encontrada.',
            ], 422);
        }

        if (!$unit->owner) {
            return response()->json([
                'ok' => false,
                'message' => 'A unidade selecionada precisa ter um proprietário vinculado para atualizar a OS.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($validated, $collection, $unit, $user, $request) {
                $payload = $this->buildCollectionPayload($validated, $unit, $user, $collection);
                $payload['updated_by'] = $user->id;
                $collection->update($payload);

                if (array_key_exists('quotas', $validated)) {
                    $this->syncCaseQuotas($collection, $validated['quotas']);
                }

                $this->syncCaseContactsFromUnit($collection, $unit);
                $this->recordTimeline($collection, 'update', 'Dados principais da OS atualizados pelo aplicativo.', $user, $request, now());
                $this->logAction($request, 'update_cobranca_case_hub', $collection->id, 'Atualização da OS de cobrança pelo aplicativo - ' . $collection->os_number, $user);
            });
        } catch (\Throwable) {
            return $this->serverErrorResponse('Não foi possível atualizar a OS agora. Tente novamente.');
        }

        return response()->json([
            'ok' => true,
            'message' => 'OS atualizada com sucesso.',
            'item' => $this->presentCollectionDetail($collection->fresh(), $user),
        ]);
    }

    public function monetaryPreview(
        Request $request,
        CobrancaCase $collection,
        CobrancaMonetaryUpdateService $service,
    ): JsonResponse {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.monetary.preview'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->monetaryStorageReady()) {
            return response()->json([
                'ok' => false,
                'message' => 'A estrutura da memória de cálculo TJES ainda não está disponível nesta instância.',
            ], 409);
        }

        $validated = $this->validateMonetaryRequest($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $calculation = $service->calculate(
                $collection->loadMissing('condominium', 'quotas'),
                $this->buildMonetaryOptions($validated, $collection),
            );
        } catch (\Throwable $throwable) {
            return response()->json([
                'ok' => false,
                'message' => $throwable->getMessage() ?: 'Não foi possível calcular a memória TJES agora.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Simulação TJES gerada com sucesso.',
            'preview' => $service->formatPreview($calculation),
        ]);
    }

    public function monetaryApply(
        Request $request,
        CobrancaCase $collection,
        CobrancaMonetaryUpdateService $service,
    ): JsonResponse {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.monetary.store', 'cobrancas.monetary.apply'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->monetaryStorageReady()) {
            return response()->json([
                'ok' => false,
                'message' => 'A estrutura da memória de cálculo TJES ainda não está disponível nesta instância.',
            ], 409);
        }

        $validated = $this->validateMonetaryRequest($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            [$update, $calculation] = DB::transaction(function () use ($collection, $service, $validated, $user, $request) {
                $calculation = $service->calculate(
                    $collection->loadMissing('condominium', 'quotas'),
                    $this->buildMonetaryOptions($validated, $collection),
                );

                $update = $this->persistMonetaryUpdate($collection, $calculation, $user->id);
                $this->applyMonetaryUpdateToCase($collection, $update, $user->id);
                $this->recordTimeline(
                    $collection,
                    'atualizacao_tjes',
                    'Memória de cálculo TJES gerada e aplicada pelo aplicativo. Total geral: ' . $this->centsToMoney((int) ($calculation['totals']['grand_total_cents'] ?? 0)) . '.',
                    $user,
                    $request,
                    now(),
                );
                $this->logAction($request, 'apply_cobranca_monetary_update_hub', $collection->id, 'Atualização monetária TJES aplicada pelo aplicativo - ' . $collection->os_number, $user);

                return [$update, $calculation];
            });
        } catch (\Throwable $throwable) {
            return response()->json([
                'ok' => false,
                'message' => $throwable->getMessage() ?: 'Não foi possível aplicar o cálculo TJES agora.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Cálculo TJES aplicado com sucesso.',
            'item' => $this->presentCollectionDetail($collection->fresh(), $user),
            'preview' => $service->formatPreview($calculation),
            'update_id' => $update->id,
        ]);
    }

    public function requestBoleto(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.boleto.request'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $collection->loadMissing([
            'condominium.administradora',
            'unit.owner',
            'unit.tenant',
            'contacts',
            'quotas',
            'installments',
            'monetaryUpdates.items',
        ]);

        if (($collection->case_mode ?: 'condominial') === 'avulsa') {
            return response()->json([
                'ok' => false,
                'message' => 'A modalidade avulsa não utiliza este fluxo de solicitação de boleto.',
            ], 422);
        }

        $administradora = $collection->condominium?->administradora;
        if (!$administradora) {
            return response()->json([
                'ok' => false,
                'message' => 'Vincule a administradora do condomínio antes de solicitar boletos.',
            ], 422);
        }

        $recipients = $this->billingAdminEmails($administradora);
        if ($recipients === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Cadastre ao menos um e-mail do setor de cobrança na administradora vinculada ao condomínio.',
            ], 422);
        }

        if (!AncoraBillingMail::isSmtpConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'O SMTP de cobrança ainda não foi configurado nesta instância.',
            ], 422);
        }

        $preferredUpdate = $this->preferredMonetaryUpdate($collection);
        if (!$preferredUpdate) {
            return response()->json([
                'ok' => false,
                'message' => 'Salve ou aplique uma memória de cálculo TJES antes de solicitar boletos.',
            ], 422);
        }

        $subject = 'Solicitação de boleto - OS ' . ($collection->os_number ?: ('OS #' . $collection->id));
        $html = $this->boletoRequestHtml($collection, $preferredUpdate);
        $result = AncoraBillingMail::sendHtml([
            'subject' => $subject,
            'html' => $html,
            'to' => $recipients,
        ]);

        if ($this->tableExists('cobranca_case_email_histories')) {
            CobrancaCaseEmailHistory::query()->create([
                'cobranca_case_id' => $collection->id,
                'cobranca_monetary_update_id' => $preferredUpdate->id,
                'sent_by' => $user->id,
                'from_address' => AncoraBillingMail::smtp()['from_address'] ?? null,
                'from_name' => AncoraBillingMail::smtp()['from_name'] ?? null,
                'subject' => Str::limit($subject, 255, ''),
                'recipients_json' => $recipients,
                'body_html' => $html,
                'send_status' => (string) ($result['send_status'] ?? 'failed'),
                'transport_message' => Str::limit((string) ($result['transport_message'] ?? ''), 65535, ''),
                'imap_status' => (string) ($result['imap_status'] ?? 'not_attempted'),
                'imap_message' => Str::limit((string) ($result['imap_message'] ?? ''), 65535, ''),
            ]);
        }

        if (($result['send_status'] ?? 'failed') !== 'sent') {
            return response()->json([
                'ok' => false,
                'message' => 'Não foi possível enviar a solicitação de boleto agora.',
                'detail' => app()->environment('local') ? ($result['transport_message'] ?? null) : null,
            ], 422);
        }

        DB::transaction(function () use ($collection, $user, $request, $recipients) {
            if ((float) ($collection->entry_amount ?? 0) > 0 && !in_array((string) $collection->entry_status, ['pago', 'boleto_enviado'], true)) {
                $collection->forceFill([
                    'entry_status' => 'boleto_solicitado',
                    'updated_by' => $user->id,
                ])->save();
            }

            CobrancaCaseInstallment::query()
                ->where('cobranca_case_id', $collection->id)
                ->where(function (Builder $query) {
                    $query->whereNull('status')
                        ->orWhere('status', '')
                        ->orWhere('status', 'pendente')
                        ->orWhere('status', 'boleto_solicitado');
                })
                ->update(['status' => 'boleto_solicitado']);

            $this->recordTimeline(
                $collection,
                'boleto',
                'Solicitação de boletos enviada à administradora para ' . $this->formatEmailList($recipients) . '.',
                $user,
                $request,
                now(),
            );
            $this->logAction($request, 'request_cobranca_boleto_hub', $collection->id, 'Solicitação de boletos enviada pelo aplicativo para ' . $this->formatEmailList($recipients) . '.', $user);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Solicitação de boleto enviada com sucesso.',
        ]);
    }

    public function installments(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $items = CobrancaCaseInstallment::query()
            ->where('cobranca_case_id', $collection->id)
            ->orderBy('due_date')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCaseInstallment $installment) => HubModulePresenter::collectionInstallment($installment))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    public function timeline(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $items = CobrancaCaseTimeline::query()
            ->with(['user'])
            ->where('cobranca_case_id', $collection->id)
            ->latest('created_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCaseTimeline $timeline) => HubModulePresenter::collectionTimeline($timeline))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    public function attachments(Request $request, CobrancaCase $collection): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['cobrancas.show', 'cobrancas.index'],
            moduleSlugs: ['cobrancas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $items = CobrancaCaseAttachment::query()
            ->with(['uploader'])
            ->where('cobranca_case_id', $collection->id)
            ->latest('created_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (CobrancaCaseAttachment $attachment) => HubModulePresenter::collectionAttachment($attachment))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    private function presentCollectionDetail(CobrancaCase $collection, User $user): array
    {
        $collection->load([
            'condominium.syndic',
            'condominium.administradora',
            'block',
            'unit.owner',
            'unit.tenant',
            'debtor',
            'contacts',
            'quotas',
        ]);

        try {
            $collection->load([
                'agreementTerm',
                'signatureRequests.signers',
                'monetaryUpdates.items',
            ]);
        } catch (\Throwable) {
        }

        return array_merge(HubModulePresenter::collectionDetail($collection), [
            'available_actions' => [
                'can_edit' => $this->userCanAnyRoute($user, ['cobrancas.edit', 'cobrancas.update']),
                'can_calculate_tjes' => $this->userCanAnyRoute($user, ['cobrancas.monetary.preview', 'cobrancas.monetary.store', 'cobrancas.monetary.apply']),
                'can_request_boleto' => $this->userCanAnyRoute($user, ['cobrancas.boleto.request']),
            ],
            'options' => [
                'charge_types' => $this->chargeTypeOptions(),
                'workflow_stages' => collect(HubModulePresenter::collectionWorkflowStageLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'billing_statuses' => collect(HubModulePresenter::collectionBillingStatusLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    private function validateCollectionRequest(Request $request, bool $creating): array|JsonResponse
    {
        $rules = [
            'unit_id' => [$creating ? 'required' : 'nullable', 'integer', 'exists:client_units,id'],
            'charge_type' => ['required', 'string', 'in:extrajudicial,judicial'],
            'workflow_stage' => ['required', 'string', 'in:' . implode(',', array_keys(HubModulePresenter::collectionWorkflowStageLabels()))],
            'billing_status' => ['required', 'string', 'in:' . implode(',', array_keys(HubModulePresenter::collectionBillingStatusLabels()))],
            'agreement_total' => ['nullable', 'numeric', 'min:0'],
            'billing_date' => ['nullable', 'date'],
            'alert_message' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'entry_status' => ['nullable', 'string', 'max:40'],
            'entry_due_date' => ['nullable', 'date'],
            'entry_amount' => ['nullable', 'numeric', 'min:0'],
            'fees_amount' => ['nullable', 'numeric', 'min:0'],
            'judicial_case_number' => ['nullable', 'string', 'max:255'],
            'calc_base_date' => ['nullable', 'date'],
            'quotas' => [$creating ? 'required' : 'nullable', 'array', 'min:1'],
            'quotas.*.reference_label' => ['required_with:quotas', 'string', 'max:100'],
            'quotas.*.due_date' => ['required_with:quotas', 'date'],
            'quotas.*.original_amount' => ['required_with:quotas', 'numeric', 'min:0'],
            'quotas.*.updated_amount' => ['nullable', 'numeric', 'min:0'],
            'quotas.*.status' => ['nullable', 'string', 'max:80'],
        ];

        $attributes = [
            'unit_id' => 'unidade',
            'charge_type' => 'tipo de cobrança',
            'billing_status' => 'status de faturamento',
            'judicial_case_number' => 'número do processo',
            'quotas' => 'cotas',
            'quotas.*.reference_label' => 'referência da cota',
            'quotas.*.due_date' => 'vencimento da cota',
            'quotas.*.original_amount' => 'valor original da cota',
            'quotas.*.updated_amount' => 'valor atualizado da cota',
        ];

        $validated = $this->validateRequest($request, $rules, attributes: $attributes);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        if (($validated['charge_type'] ?? 'extrajudicial') === 'judicial' && empty($validated['judicial_case_number'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Informe o número do processo para a cobrança judicializada.',
            ], 422);
        }

        return $validated;
    }

    private function validateMonetaryRequest(Request $request): array|JsonResponse
    {
        return $this->validateRequest(
            $request,
            [
                'final_date' => ['nullable', 'date'],
                'index_code' => ['nullable', 'string', 'max:20'],
                'quota_ids' => ['nullable', 'array'],
                'quota_ids.*' => ['integer'],
                'interest_type' => ['nullable', 'string', 'in:legal,contractual,none'],
                'interest_rate_monthly' => ['nullable', 'numeric', 'min:0'],
                'fine_percent' => ['nullable', 'numeric', 'min:0'],
                'attorney_fee_type' => ['nullable', 'string', 'in:percent,fixed,none'],
                'attorney_fee_value' => ['nullable', 'numeric', 'min:0'],
                'costs_amount' => ['nullable', 'numeric', 'min:0'],
                'costs_date' => ['nullable', 'date'],
                'abatement_amount' => ['nullable', 'numeric', 'min:0'],
            ],
            attributes: [
                'final_date' => 'data final',
                'index_code' => 'índice',
                'quota_ids' => 'cotas selecionadas',
                'interest_type' => 'tipo de juros',
                'interest_rate_monthly' => 'juros mensais',
                'fine_percent' => 'multa',
                'attorney_fee_type' => 'tipo de honorários',
                'attorney_fee_value' => 'honorários',
                'costs_amount' => 'custas',
                'costs_date' => 'data das custas',
                'abatement_amount' => 'abatimento',
            ],
        );
    }

    private function findCollectionUnit(int $unitId): ?ClientUnit
    {
        if ($unitId <= 0) {
            return null;
        }

        return ClientUnit::query()
            ->with(['condominium', 'block', 'owner', 'tenant'])
            ->find($unitId);
    }

    private function buildCollectionPayload(
        array $validated,
        ClientUnit $unit,
        User $user,
        ?CobrancaCase $current = null,
    ): array {
        $workflowStage = trim((string) ($validated['workflow_stage'] ?? $current?->workflow_stage ?: 'triagem'));
        $owner = $unit->owner;
        $primaryEmail = $this->extractPrimaryEmail($owner);
        $primaryPhone = $this->extractPrimaryPhone($owner);

        return [
            'case_mode' => 'condominial',
            'condominium_id' => $unit->condominium_id,
            'block_id' => $unit->block_id,
            'unit_id' => $unit->id,
            'debtor_entity_id' => $owner?->id,
            'debtor_role' => 'owner',
            'debtor_name_snapshot' => (string) ($owner?->display_name ?: ''),
            'debtor_document_snapshot' => (string) ($owner?->cpf_cnpj ?: ''),
            'debtor_email_snapshot' => $primaryEmail,
            'debtor_phone_snapshot' => $primaryPhone,
            'charge_type' => trim((string) ($validated['charge_type'] ?? $current?->charge_type ?: 'extrajudicial')),
            'agreement_total' => $this->decimalOrNull($validated['agreement_total'] ?? $current?->agreement_total),
            'billing_status' => trim((string) ($validated['billing_status'] ?? $current?->billing_status ?: 'a_faturar')),
            'billing_date' => $validated['billing_date'] ?? $current?->billing_date?->toDateString(),
            'alert_message' => trim((string) ($validated['alert_message'] ?? $current?->alert_message ?: '')) ?: null,
            'notes' => trim((string) ($validated['notes'] ?? $current?->notes ?: '')) ?: null,
            'situation' => $this->situationFromWorkflowStage($workflowStage),
            'workflow_stage' => $workflowStage,
            'entry_status' => trim((string) ($validated['entry_status'] ?? $current?->entry_status ?: '')) ?: null,
            'entry_due_date' => $validated['entry_due_date'] ?? $current?->entry_due_date?->toDateString(),
            'entry_amount' => $this->decimalOrNull($validated['entry_amount'] ?? $current?->entry_amount),
            'fees_amount' => $this->decimalOrNull($validated['fees_amount'] ?? $current?->fees_amount),
            'judicial_case_number' => trim((string) ($validated['judicial_case_number'] ?? $current?->judicial_case_number ?: '')) ?: null,
            'calc_base_date' => $validated['calc_base_date'] ?? $current?->calc_base_date?->toDateString(),
            'updated_by' => $user->id,
        ];
    }

    private function syncCaseQuotas(CobrancaCase $case, array $quotas): void
    {
        CobrancaCaseQuota::query()
            ->where('cobranca_case_id', $case->id)
            ->delete();

        foreach ($quotas as $quota) {
            CobrancaCaseQuota::query()->create([
                'cobranca_case_id' => $case->id,
                'reference_label' => trim((string) ($quota['reference_label'] ?? '')),
                'due_date' => $quota['due_date'] ?? null,
                'original_amount' => $this->decimalOrNull($quota['original_amount'] ?? null) ?? 0,
                'updated_amount' => $this->decimalOrNull($quota['updated_amount'] ?? null),
                'status' => trim((string) ($quota['status'] ?? 'pendente')) ?: 'pendente',
                'created_at' => now(),
            ]);
        }
    }

    private function syncCaseContactsFromUnit(CobrancaCase $case, ClientUnit $unit): void
    {
        CobrancaCaseContact::query()
            ->where('cobranca_case_id', $case->id)
            ->delete();

        $contacts = [];
        foreach ([$unit->owner, $unit->tenant] as $entity) {
            if (!$entity instanceof ClientEntity) {
                continue;
            }

            foreach ($this->extractEmails($entity) as $index => $email) {
                $contacts[] = [
                    'cobranca_case_id' => $case->id,
                    'contact_type' => 'email',
                    'label' => $entity->display_name ? ('E-mail de ' . $entity->display_name) : 'E-mail',
                    'value' => $email,
                    'is_primary' => $index === 0,
                    'is_whatsapp' => false,
                    'created_at' => now(),
                ];
            }

            foreach ($this->extractPhones($entity) as $index => $phone) {
                $contacts[] = [
                    'cobranca_case_id' => $case->id,
                    'contact_type' => 'phone',
                    'label' => $entity->display_name ? ('Telefone de ' . $entity->display_name) : 'Telefone',
                    'value' => $phone,
                    'is_primary' => $index === 0,
                    'is_whatsapp' => true,
                    'created_at' => now(),
                ];
            }
        }

        if ($contacts !== []) {
            CobrancaCaseContact::query()->insert($contacts);
        }
    }

    private function extractPrimaryEmail(?ClientEntity $entity): ?string
    {
        return $this->extractEmails($entity)[0] ?? null;
    }

    private function extractPrimaryPhone(?ClientEntity $entity): ?string
    {
        return $this->extractPhones($entity)[0] ?? null;
    }

    private function extractEmails(?ClientEntity $entity): array
    {
        return collect((array) ($entity?->emails_json ?? []))
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

    private function extractPhones(?ClientEntity $entity): array
    {
        return collect((array) ($entity?->phones_json ?? []))
            ->map(function ($row) {
                if (is_array($row)) {
                    return trim((string) ($row['number'] ?? ''));
                }

                return trim((string) $row);
            })
            ->filter(fn ($phone) => $phone !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function nextChargeYear(?string $baseDate): int
    {
        if ($baseDate && strtotime($baseDate) !== false) {
            return (int) date('Y', strtotime($baseDate));
        }

        return (int) now()->format('Y');
    }

    private function nextChargeSequence(int $year): int
    {
        return (int) CobrancaCase::query()
            ->where('charge_year', $year)
            ->max('charge_seq') + 1;
    }

    private function buildMonetaryOptions(array $validated, CobrancaCase $case): array
    {
        $case->loadMissing('condominium');

        return [
            'final_date' => $validated['final_date'] ?? null,
            'index_code' => $validated['index_code'] ?? 'ATM',
            'quota_ids' => $validated['quota_ids'] ?? [],
            'interest_type' => $validated['interest_type'] ?? 'legal',
            'interest_rate_monthly' => $validated['interest_rate_monthly'] ?? null,
            'fine_percent' => $validated['fine_percent'] ?? 0,
            'attorney_fee_type' => $validated['attorney_fee_type'] ?? 'percent',
            'attorney_fee_value' => $validated['attorney_fee_value'] ?? 0,
            'costs_amount' => $validated['costs_amount'] ?? 0,
            'costs_date' => $validated['costs_date'] ?? null,
            'abatement_amount' => $validated['abatement_amount'] ?? 0,
            'boleto_fee_amount' => $case->condominium?->boleto_fee_amount,
            'boleto_cancellation_fee_amount' => $case->condominium?->boleto_cancellation_fee_amount,
            'apply_boleto_fee' => config('automation.collection.boleto_fees.enabled', true),
            'apply_boleto_cancellation_fee' => config('automation.collection.boleto_fees.cancellation_enabled', true),
        ];
    }

    private function monetaryStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_monetary_index_factors')
                && Schema::hasTable('cobranca_monetary_updates')
                && Schema::hasTable('cobranca_monetary_update_items');
        } catch (\Throwable) {
            return false;
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
                ? round(((int) $settings['attorney_fee_value']) / 100, 2)
                : (float) $settings['attorney_fee_value'],
            'costs_amount' => round(((int) $settings['costs_cents']) / 100, 2),
            'costs_date' => $settings['costs_date']?->toDateString(),
            'costs_corrected_amount' => round(((int) $totals['costs_corrected_cents']) / 100, 2),
            'abatement_amount' => round(((int) $totals['abatement_cents']) / 100, 2),
            'original_total' => round(((int) $totals['original_cents']) / 100, 2),
            'corrected_total' => round(((int) $totals['corrected_cents']) / 100, 2),
            'interest_total' => round(((int) $totals['interest_cents']) / 100, 2),
            'fine_total' => round(((int) $totals['fine_cents']) / 100, 2),
            'debit_total' => round(((int) $totals['debit_total_cents']) / 100, 2),
            'attorney_fee_amount' => round(((int) $totals['attorney_fee_cents']) / 100, 2),
            'grand_total' => round(((int) $totals['grand_total_cents']) / 100, 2),
            'payload_json' => [
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
                'summary' => $calculation['summary'],
                'totals_cents' => $totals,
            ],
            'generated_by' => $userId,
        ];

        if ($this->monetaryUpdateHasColumn('boleto_fee_total')) {
            $payload['boleto_fee_total'] = round(((int) $totals['boleto_fee_cents']) / 100, 2);
        }

        if ($this->monetaryUpdateHasColumn('boleto_cancellation_fee_total')) {
            $payload['boleto_cancellation_fee_total'] = round(((int) $totals['boleto_cancellation_fee_cents']) / 100, 2);
        }

        /** @var CobrancaMonetaryUpdate $update */
        $update = CobrancaMonetaryUpdate::query()->create($payload);

        foreach ($calculation['items'] as $item) {
            CobrancaMonetaryUpdateItem::query()->create([
                'cobranca_monetary_update_id' => $update->id,
                'cobranca_case_quota_id' => $item['quota_id'],
                'reference_label' => Str::limit((string) $item['reference_label'], 100, ''),
                'due_date' => $item['due_date']->toDateString(),
                'original_amount' => round(((int) $item['original_cents']) / 100, 2),
                'correction_factor' => $item['correction_factor'],
                'corrected_amount' => round(((int) $item['corrected_cents']) / 100, 2),
                'interest_months' => $item['interest_months'],
                'interest_percent' => $item['interest_percent'],
                'interest_amount' => round(((int) $item['interest_cents']) / 100, 2),
                'fine_percent' => $item['fine_percent'],
                'fine_amount' => round(((int) $item['fine_cents']) / 100, 2),
                'total_amount' => round(((int) $item['total_cents']) / 100, 2),
                'created_at' => now(),
            ]);
        }

        return $update->load('items');
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

    private function monetaryUpdateHasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn('cobranca_monetary_updates', $column);
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

    private function preferredMonetaryUpdate(CobrancaCase $case): ?CobrancaMonetaryUpdate
    {
        if (!$this->monetaryStorageReady()) {
            return null;
        }

        $updates = $case->relationLoaded('monetaryUpdates')
            ? $case->monetaryUpdates
            : $case->monetaryUpdates()->with('items')->get();

        if ($updates->isEmpty()) {
            return null;
        }

        return $updates->firstWhere('applied_to_case', true) ?: $updates->first();
    }

    private function boletoRequestHtml(CobrancaCase $case, CobrancaMonetaryUpdate $update): string
    {
        $condominium = trim((string) ($case->condominium?->name ?? 'Condomínio não informado'));
        $unit = trim((string) ($case->unit?->unit_label ?: $case->unit?->unit_number ?: 'Sem unidade'));
        $debtor = trim((string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name ?: 'Não informado'));
        $total = $update->grand_total !== null
            ? 'R$ ' . number_format((float) $update->grand_total, 2, ',', '.')
            : 'Não informado';
        $baseDate = $update->final_date?->format('d/m/Y') ?: 'Não informada';

        return <<<HTML
<html>
    <body style="font-family: Arial, sans-serif; color: #1f2937;">
        <h2 style="margin-bottom: 8px;">Solicitação de boleto</h2>
        <p>Prezados,</p>
        <p>Solicitamos a emissão dos boletos relacionados à OS <strong>{$case->os_number}</strong>.</p>
        <p><strong>Condomínio:</strong> {$condominium}<br>
        <strong>Unidade:</strong> {$unit}<br>
        <strong>Devedor:</strong> {$debtor}<br>
        <strong>Total atualizado:</strong> {$total}<br>
        <strong>Base do cálculo TJES:</strong> {$baseDate}</p>
        <p>Atenciosamente,<br>Equipe Âncora Hub</p>
    </body>
</html>
HTML;
    }

    private function recordTimeline(
        CobrancaCase $case,
        string $eventType,
        string $description,
        ?User $user,
        Request $request,
        mixed $timestamp,
    ): void {
        CobrancaCaseTimeline::query()->create([
            'cobranca_case_id' => $case->id,
            'event_type' => Str::limit($eventType, 40, ''),
            'description' => $description,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'created_at' => $timestamp,
        ]);

        $case->updateQuietly([
            'last_progress_at' => $timestamp,
            'updated_by' => $user?->id,
        ]);
        $request->attributes->set('audit.skip_generic', true);
    }

    private function logAction(Request $request, string $action, int $entityId, string $details, ?User $user): void
    {
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

    private function chargeTypeOptions(): array
    {
        return [
            HubModulePresenter::statusOption('extrajudicial', 'Cobrança extrajudicial'),
            HubModulePresenter::statusOption('judicial', 'Cobrança judicial'),
        ];
    }

    private function situationFromWorkflowStage(string $stage): string
    {
        return match ($stage) {
            'acordo_ativo', 'aguardando_boletos' => 'em_pagamento_acordo',
            'acordo_inadimplido' => 'acordo_nao_pago',
            'judicializado' => 'ajuizado',
            'pago_encerrado' => 'pago_encerrado',
            'cancelado' => 'cancelado',
            default => 'processo_aberto',
        };
    }

    private function formatEmailList(array $emails): string
    {
        return collect($emails)
            ->filter()
            ->values()
            ->implode(', ');
    }

    private function centsToMoney(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }
}
