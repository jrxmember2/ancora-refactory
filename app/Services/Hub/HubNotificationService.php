<?php

namespace App\Services\Hub;

use App\Jobs\SendHubPushNotificationJob;
use App\Models\Demand;
use App\Models\HubNotification;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use App\Models\User;
use App\Services\Mobile\FirebaseCloudMessagingService;
use Illuminate\Support\Collection;

class HubNotificationService
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $fcmService,
    ) {
    }

    public function notifyDemandCreated(Demand $demand): void
    {
        $title = 'Nova demanda';
        $subject = trim((string) ($demand->subject ?? ''));
        $reference = trim((string) ($demand->protocol ?: ('Demanda #' . $demand->id)));
        $body = $subject !== '' ? "{$reference}: {$subject}" : $reference;

        $this->createForModuleUsers(
            moduleSlug: 'demandas',
            type: 'nova_demanda',
            title: $title,
            body: $body,
            data: [
                'route' => 'demands',
                'module' => 'demandas',
                'demand_id' => (string) $demand->id,
            ],
            entityType: Demand::class,
            entityId: (int) $demand->id,
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
                'route' => 'processes',
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
                'route' => 'processes',
                'module' => 'processos',
                'process_id' => (string) $case->id,
            ],
            entityType: ProcessCase::class,
            entityId: (int) $case->id,
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
        $notification = HubNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'module' => $module,
            'data_json' => $data,
        ]);

        $this->dispatchPush($notification);

        return $notification;
    }

    private function createForModuleUsers(
        string $moduleSlug,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null,
    ): void {
        $this->audienceForModule($moduleSlug)->each(function (User $user) use (
            $type,
            $moduleSlug,
            $title,
            $body,
            $data,
            $entityType,
            $entityId,
            $actionUrl,
        ) {
            $notification = HubNotification::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'module' => $moduleSlug,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action_url' => $actionUrl,
                'data_json' => $data,
            ]);

            $this->dispatchPush($notification);
        });
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

    private function dispatchPush(HubNotification $notification): void
    {
        if (!$this->fcmService->enabled()) {
            return;
        }

        SendHubPushNotificationJob::dispatch($notification->id)->afterCommit();
    }
}
