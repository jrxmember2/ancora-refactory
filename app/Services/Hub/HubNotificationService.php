<?php

namespace App\Services\Hub;

use App\Models\CobrancaCase;
use App\Models\Contract;
use App\Models\Demand;
use App\Models\DemandMessage;
use App\Models\DocumentSignatureRequest;
use App\Models\FinancialReceivable;
use App\Models\HubNotification;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HubNotificationService
{
    public function __construct(
        private readonly HubPushNotificationService $pushNotifications,
    ) {
    }

    public function notifyDemandCreated(Demand $demand): void
    {
        $title = 'Nova demanda recebida';
        $subject = trim((string) ($demand->subject ?? ''));
        $reference = $this->demandReference($demand);
        $body = $subject !== '' ? "{$reference}: {$subject}" : $reference;

        $this->createForModuleUsers(
            moduleSlug: 'demandas',
            type: 'nova_demanda',
            title: $title,
            body: $body,
            data: [
                'route' => $this->hubRoute("demands/{$demand->id}"),
                'module' => 'demandas',
                'demand_id' => (string) $demand->id,
            ],
            entityType: Demand::class,
            entityId: (int) $demand->id,
        );
    }

    public function notifyDemandReply(DemandMessage $message): void
    {
        $message->loadMissing(['demand.condominium', 'demand.entity', 'portalUser', 'user']);
        $demand = $message->demand;

        if (!$demand) {
            return;
        }

        $sender = trim((string) $message->senderName());
        $snippet = Str::limit(
            preg_replace('/\s+/u', ' ', trim((string) ($message->message ?? ''))) ?: '',
            120,
            '...'
        );

        $body = "{$this->demandReference($demand)}: nova resposta";
        if ($sender !== '') {
            $body .= " de {$sender}";
        }
        if ($snippet !== '') {
            $body .= " - {$snippet}";
        }

        $audience = $this->audienceForModule('demandas')
            ->reject(fn (User $user) => $message->user_id && (int) $user->id === (int) $message->user_id)
            ->values();

        if ($audience->isEmpty()) {
            return;
        }

        $this->createForModuleUsers(
            moduleSlug: 'demandas',
            type: 'resposta_demanda',
            title: 'Nova resposta em demanda',
            body: $body,
            data: [
                'route' => $this->hubRoute("demands/{$demand->id}"),
                'module' => 'demandas',
                'demand_id' => (string) $demand->id,
                'demand_message_id' => (string) $message->id,
            ],
            entityType: DemandMessage::class,
            entityId: (int) $message->id,
            users: $audience,
        );
    }

    public function notifyProcessPhaseCreated(ProcessCasePhase $phase): void
    {
        $case = $phase->processCase;
        if (!$case || $case->is_private || $phase->is_private) {
            return;
        }

        $title = 'Novo andamento processual';
        $reference = trim((string) ($case->process_number ?: ('Processo #' . $case->id)));
        $description = trim((string) ($phase->description ?? 'Novo andamento registrado.'));
        $body = "{$reference}: {$description}";

        $this->createForModuleUsers(
            moduleSlug: 'processos',
            type: 'novo_andamento_processual',
            title: $title,
            body: $body,
            data: [
                'route' => $this->hubRoute("processes/{$case->id}"),
                'module' => 'processos',
                'process_id' => (string) $case->id,
                'phase_id' => (string) $phase->id,
            ],
            entityType: ProcessCasePhase::class,
            entityId: (int) $phase->id,
        );
    }

    public function notifyProcessStatusChanged(ProcessCase $case): void
    {
        if ($case->is_private) {
            return;
        }

        $title = 'Processo atualizado';
        $reference = trim((string) ($case->process_number ?: ('Processo #' . $case->id)));
        $status = trim((string) ($case->statusOption?->name ?: 'Status atualizado'));

        $this->createForModuleUsers(
            moduleSlug: 'processos',
            type: 'processo_atualizado',
            title: $title,
            body: "{$reference}: {$status}",
            data: [
                'route' => $this->hubRoute("processes/{$case->id}"),
                'module' => 'processos',
                'process_id' => (string) $case->id,
            ],
            entityType: ProcessCase::class,
            entityId: (int) $case->id,
        );
    }

    public function notifyCollectionEligibleForJudicialization(CobrancaCase $case): void
    {
        $reference = $this->collectionReference($case);

        $this->createForModuleUsers(
            moduleSlug: 'cobrancas',
            type: 'cobranca_apta_judicializacao',
            title: 'Cobrança apta para judicialização',
            body: "{$reference}: a cobrança atingiu os critérios para judicialização.",
            data: [
                'route' => $this->hubRoute("collections/{$case->id}"),
                'module' => 'cobrancas',
                'collection_id' => (string) $case->id,
            ],
            entityType: CobrancaCase::class,
            entityId: (int) $case->id,
        );
    }

    public function notifyOverdueAgreement(CobrancaCase $case): void
    {
        $reference = $this->collectionReference($case);

        $this->createForModuleUsers(
            moduleSlug: 'cobrancas',
            type: 'acordo_vencido',
            title: 'Acordo vencido',
            body: "{$reference}: existe parcela de acordo vencida aguardando regularização.",
            data: [
                'route' => $this->hubRoute("collections/{$case->id}"),
                'module' => 'cobrancas',
                'collection_id' => (string) $case->id,
            ],
            entityType: CobrancaCase::class,
            entityId: (int) $case->id,
        );
    }

    public function notifySignatureCompleted(DocumentSignatureRequest $signature): void
    {
        $signature->loadMissing('signable');
        [$module, $route, $extraData] = $this->signatureRouting($signature);

        $this->createForModuleUsers(
            moduleSlug: 'assinador',
            type: 'assinatura_concluida',
            title: 'Assinatura concluída',
            body: $this->signatureSourceName($signature) . ': documento assinado com sucesso.',
            data: array_merge([
                'route' => $route,
                'module' => $module,
                'signature_id' => (string) $signature->id,
            ], $extraData),
            entityType: DocumentSignatureRequest::class,
            entityId: (int) $signature->id,
        );
    }

    public function notifyPendingContract(Contract $contract, ?DocumentSignatureRequest $signature = null): void
    {
        $reference = trim((string) ($contract->code ?: $contract->title ?: ('Contrato #' . $contract->id)));

        $this->createForModuleUsers(
            moduleSlug: 'contratos',
            type: 'contrato_pendente',
            title: 'Contrato pendente',
            body: "{$reference}: aguardando assinatura ou acompanhamento.",
            data: array_filter([
                'route' => $this->hubRoute("contracts/{$contract->id}"),
                'module' => 'contratos',
                'contract_id' => (string) $contract->id,
                'signature_id' => $signature?->id ? (string) $signature->id : null,
            ], fn ($value) => $value !== null && $value !== ''),
            entityType: Contract::class,
            entityId: (int) $contract->id,
        );
    }

    public function notifyOverdueReceivable(FinancialReceivable $receivable): void
    {
        $reference = trim((string) ($receivable->code ?: ('Recebível #' . $receivable->id)));
        $dueDate = $receivable->due_date?->format('d/m/Y');
        $body = "{$reference}: conta vencida";

        if ($dueDate) {
            $body .= " em {$dueDate}";
        }

        $this->createForModuleUsers(
            moduleSlug: 'financeiro',
            type: 'conta_vencida',
            title: 'Conta vencida',
            body: $body . '.',
            data: [
                'route' => $this->hubRoute("finance/receivables/{$receivable->id}"),
                'module' => 'financeiro',
                'receivable_id' => (string) $receivable->id,
            ],
            entityType: FinancialReceivable::class,
            entityId: (int) $receivable->id,
        );
    }

    public function createGeneralNoticeForUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        ?string $type = 'notificacao_geral',
        ?string $module = 'hub',
    ): HubNotification {
        return $this->pushNotifications->createForUser(
            user: $user,
            title: $title,
            body: $body,
            data: $data,
            type: $type,
            module: $module,
        );
    }

    /**
     * @param iterable<int, User>|null $users
     */
    private function createForModuleUsers(
        string $moduleSlug,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null,
        ?iterable $users = null,
    ): void {
        $audience = $users !== null ? collect($users) : $this->audienceForModule($moduleSlug);
        if ($audience->isEmpty()) {
            return;
        }

        $this->pushNotifications->createForUsers(
            users: $audience,
            title: $title,
            body: $body,
            data: $data,
            type: $type,
            module: $moduleSlug,
            entityType: $entityType,
            entityId: $entityId,
            actionUrl: $actionUrl,
        );
    }

    private function audienceForModule(string $moduleSlug): Collection
    {
        return User::query()
            ->active()
            ->where(function ($query) use ($moduleSlug) {
                $query->where('role', 'superadmin')
                    ->orWhereHas('modules', function ($moduleQuery) use ($moduleSlug) {
                        $moduleQuery
                            ->where('system_modules.slug', $moduleSlug)
                            ->where('system_modules.is_enabled', 1);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    private function demandReference(Demand $demand): string
    {
        return trim((string) ($demand->protocol ?: ('Demanda #' . $demand->id)));
    }

    private function collectionReference(CobrancaCase $case): string
    {
        return trim((string) ($case->os_number ?: ('Cobrança #' . $case->id)));
    }

    private function signatureSourceName(DocumentSignatureRequest $signature): string
    {
        $signable = $signature->signable;

        if ($signable instanceof Contract) {
            return trim((string) ($signable->code ?: $signable->title ?: ('Contrato #' . $signable->id)));
        }

        if ($signable instanceof CobrancaCase) {
            return trim((string) ($signable->os_number ?: ('Cobrança #' . $signable->id)));
        }

        return trim((string) ($signature->document_name ?: ('Documento #' . $signature->id)));
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, string>}
     */
    private function signatureRouting(DocumentSignatureRequest $signature): array
    {
        $signable = $signature->signable;

        if ($signable instanceof Contract) {
            return [
                'assinador',
                $this->hubRoute("signatures/{$signature->id}"),
                [
                    'contract_id' => (string) $signable->id,
                ],
            ];
        }

        if ($signable instanceof CobrancaCase) {
            return [
                'assinador',
                $this->hubRoute("signatures/{$signature->id}"),
                [
                    'collection_id' => (string) $signable->id,
                ],
            ];
        }

        return [
            'assinador',
            $this->hubRoute("signatures/{$signature->id}"),
            [],
        ];
    }

    private function hubRoute(string $path): string
    {
        return 'hub://' . ltrim($path, '/');
    }
}
