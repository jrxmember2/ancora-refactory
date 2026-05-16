<?php

namespace App\Services\Mobile;

use App\Jobs\SendClientPortalPushNotificationJob;
use App\Models\ClientPortalNotification;
use App\Models\ClientPortalUser;
use App\Models\Demand;
use App\Models\DemandMessage;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use Illuminate\Support\Collection;

class ClientPortalNotificationService
{
    public function __construct(
        private readonly ClientPortalAudienceResolver $audienceResolver,
        private readonly FirebaseCloudMessagingService $fcmService,
    ) {
    }

    public function notifyProcessPhaseCreated(ProcessCasePhase $phase): void
    {
        $case = $phase->processCase()->with(['statusOption', 'processTypeOption', 'natureOption'])->first();
        if (!$case || $case->is_private || $phase->is_private) {
            return;
        }

        $title = 'Novo andamento em processo';
        $body = ($case->process_number ?: 'Processo #' . $case->id) . ': ' . $phase->description;
        $this->createForUsers(
            $this->audienceResolver->forProcess($case),
            'process_new_phase',
            $title,
            $body,
            [
                'screen' => 'process_detail',
                'process_id' => (string) $case->id,
                'phase_id' => (string) $phase->id,
            ],
            (int) ($case->client_condominium_id ?? 0)
        );
    }

    public function notifyProcessStatusChanged(ProcessCase $case): void
    {
        if ($case->is_private || !$case->statusOption) {
            return;
        }

        $this->createForUsers(
            $this->audienceResolver->forProcess($case),
            'process_status_changed',
            'Status do processo atualizado',
            ($case->process_number ?: 'Processo #' . $case->id) . ': ' . ($case->statusOption?->name ?: 'Sem status'),
            [
                'screen' => 'process_detail',
                'process_id' => (string) $case->id,
            ],
            (int) ($case->client_condominium_id ?? 0)
        );
    }

    public function notifyDemandCreated(Demand $demand): void
    {
        $recipients = $this->audienceResolver
            ->forDemand($demand)
            ->reject(fn (ClientPortalUser $user) => (int) $user->id === (int) ($demand->client_portal_user_id ?? 0))
            ->values();

        $this->createForUsers(
            $recipients,
            'demand_created',
            'Nova solicitacao criada',
            $demand->protocol . ': ' . $demand->subject,
            [
                'screen' => 'demand_detail',
                'demand_id' => (string) $demand->id,
            ],
            (int) ($demand->client_condominium_id ?? 0)
        );
    }

    public function notifyDemandStatusChanged(Demand $demand, ?string $previousStatus = null): void
    {
        $status = (string) $demand->status;
        if ($previousStatus !== null && $previousStatus === $status) {
            return;
        }

        [$type, $title] = match ($status) {
            'aguardando_cliente' => ['demand_waiting_client', 'Solicitacao aguardando sua resposta'],
            'concluida' => ['demand_closed', 'Solicitacao concluida'],
            'cancelada' => ['demand_cancelled', 'Solicitacao cancelada'],
            default => ['demand_status_changed', 'Solicitacao atualizada'],
        };

        $this->createForUsers(
            $this->audienceResolver->forDemand($demand),
            $type,
            $title,
            $demand->protocol . ': ' . $demand->publicStatusLabel(),
            [
                'screen' => 'demand_detail',
                'demand_id' => (string) $demand->id,
                'status' => $status,
            ],
            (int) ($demand->client_condominium_id ?? 0)
        );
    }

    public function notifyDemandReply(DemandMessage $message): void
    {
        if ($message->is_internal || $message->sender_type === 'client') {
            return;
        }

        $demand = $message->demand()->with(['tag', 'category', 'condominium'])->first();
        if (!$demand) {
            return;
        }

        $this->createForUsers(
            $this->audienceResolver->forDemand($demand),
            'demand_new_message',
            'Nova resposta na solicitacao',
            $demand->protocol . ': ' . $demand->subject,
            [
                'screen' => 'demand_detail',
                'demand_id' => (string) $demand->id,
                'message_id' => (string) $message->id,
            ],
            (int) ($demand->client_condominium_id ?? 0)
        );
    }

    public function createGeneralNoticeForUser(ClientPortalUser $user, string $title, string $body, array $data = []): ClientPortalNotification
    {
        $notification = ClientPortalNotification::query()->create([
            'client_portal_user_id' => $user->id,
            'client_condominium_id' => null,
            'type' => 'general_notice',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $this->dispatchPush($notification);

        return $notification;
    }

    /** @param Collection<int, ClientPortalUser> $users */
    private function createForUsers(Collection $users, string $type, string $title, string $body, array $data, int $clientCondominiumId = 0): void
    {
        $users->unique('id')->each(function (ClientPortalUser $user) use ($type, $title, $body, $data, $clientCondominiumId) {
            $notification = ClientPortalNotification::query()->create([
                'client_portal_user_id' => $user->id,
                'client_condominium_id' => $clientCondominiumId > 0 ? $clientCondominiumId : null,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            $this->dispatchPush($notification);
        });
    }

    private function dispatchPush(ClientPortalNotification $notification): void
    {
        if (!$this->fcmService->enabled()) {
            return;
        }

        SendClientPortalPushNotificationJob::dispatch($notification->id)->afterCommit();
    }
}
