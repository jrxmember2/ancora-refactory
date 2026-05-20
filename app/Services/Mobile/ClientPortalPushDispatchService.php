<?php

namespace App\Services\Mobile;

use App\Jobs\SendClientPortalPushDispatchJob;
use App\Models\ClientPortalPushDispatch;
use App\Models\ClientPortalUser;
use App\Models\User;
use App\Support\Mobile\ClientPortalPushCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientPortalPushDispatchService
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $fcmService,
    ) {
    }

    public function queueGlobalDispatch(User $admin, array $payload): ClientPortalPushDispatch
    {
        $eligibleUsers = ClientPortalUser::query()
            ->active()
            ->whereHas('deviceTokens', fn ($query) => $query->active())
            ->orderBy('name')
            ->get(['id']);

        if (!$this->fcmService->enabled()) {
            return $this->createFailedDispatch(
                admin: $admin,
                payload: $payload,
                recipientMode: 'global',
                recipientIds: $eligibleUsers->pluck('id')->map(fn ($id) => (int) $id)->all(),
                recipientSnapshots: null,
                failureReason: 'Firebase Cloud Messaging nao esta configurado para este ambiente.',
            );
        }

        if ($eligibleUsers->isEmpty()) {
            return $this->createFailedDispatch(
                admin: $admin,
                payload: $payload,
                recipientMode: 'global',
                recipientIds: [],
                recipientSnapshots: null,
                failureReason: 'Nenhum usuario ativo com app/token disponivel foi encontrado para o envio global.',
            );
        }

        return $this->createQueuedDispatch(
            admin: $admin,
            payload: $payload,
            recipientMode: 'global',
            recipientIds: $eligibleUsers->pluck('id')->map(fn ($id) => (int) $id)->all(),
            recipientSnapshots: null,
        );
    }

    /** @param array<int,int|string> $selectedUserIds */
    public function queueSpecificDispatch(User $admin, array $payload, array $selectedUserIds): ClientPortalPushDispatch
    {
        $selectedIds = collect($selectedUserIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $users = ClientPortalUser::query()
            ->active()
            ->with(['entity', 'condominium', 'condominiums'])
            ->withCount([
                'deviceTokens as active_device_count' => fn ($query) => $query->active(),
            ])
            ->whereIn('id', $selectedIds->all())
            ->get()
            ->keyBy('id');

        $orderedUsers = $selectedIds
            ->map(fn ($id) => $users->get($id))
            ->filter()
            ->values();

        if (!$this->fcmService->enabled()) {
            return $this->createFailedDispatch(
                admin: $admin,
                payload: $payload,
                recipientMode: 'specific',
                recipientIds: $orderedUsers->pluck('id')->map(fn ($id) => (int) $id)->all(),
                recipientSnapshots: $this->recipientSnapshots($orderedUsers),
                failureReason: 'Firebase Cloud Messaging nao esta configurado para este ambiente.',
            );
        }

        if ($orderedUsers->isEmpty()) {
            return $this->createFailedDispatch(
                admin: $admin,
                payload: $payload,
                recipientMode: 'specific',
                recipientIds: [],
                recipientSnapshots: [],
                failureReason: 'Nenhum usuario valido foi encontrado para o envio selecionado.',
            );
        }

        return $this->createQueuedDispatch(
            admin: $admin,
            payload: $payload,
            recipientMode: 'specific',
            recipientIds: $orderedUsers->pluck('id')->map(fn ($id) => (int) $id)->all(),
            recipientSnapshots: $this->recipientSnapshots($orderedUsers),
        );
    }

    /** @param array<int,int> $recipientIds */
    private function createQueuedDispatch(
        User $admin,
        array $payload,
        string $recipientMode,
        array $recipientIds,
        ?array $recipientSnapshots,
    ): ClientPortalPushDispatch {
        return DB::transaction(function () use ($admin, $payload, $recipientMode, $recipientIds, $recipientSnapshots) {
            $dispatch = ClientPortalPushDispatch::query()->create([
                'created_by_user_id' => $admin->id,
                'title' => $payload['title'],
                'body' => $payload['body'],
                'notification_type' => $payload['notification_type'],
                'recipient_mode' => $recipientMode,
                'recipient_user_ids_json' => $recipientIds,
                'recipient_snapshots_json' => $recipientSnapshots,
                'payload_json' => $this->payloadJson($payload),
                'status' => 'queued',
                'total_recipients' => count($recipientIds),
                'success_count' => 0,
                'error_count' => 0,
                'invalid_token_count' => 0,
                'queued_at' => now(),
            ]);

            SendClientPortalPushDispatchJob::dispatch($dispatch->id)->afterCommit();

            return $dispatch;
        });
    }

    /** @param array<int,int> $recipientIds */
    private function createFailedDispatch(
        User $admin,
        array $payload,
        string $recipientMode,
        array $recipientIds,
        ?array $recipientSnapshots,
        string $failureReason,
    ): ClientPortalPushDispatch {
        return ClientPortalPushDispatch::query()->create([
            'created_by_user_id' => $admin->id,
            'title' => $payload['title'],
            'body' => $payload['body'],
            'notification_type' => $payload['notification_type'],
            'recipient_mode' => $recipientMode,
            'recipient_user_ids_json' => $recipientIds,
            'recipient_snapshots_json' => $recipientSnapshots,
            'payload_json' => $this->payloadJson($payload),
            'status' => 'failed',
            'total_recipients' => count($recipientIds),
            'success_count' => 0,
            'error_count' => count($recipientIds),
            'invalid_token_count' => 0,
            'queued_at' => now(),
            'finished_at' => now(),
            'failure_reason' => $failureReason,
        ]);
    }

    /** @param Collection<int, ClientPortalUser> $users */
    private function recipientSnapshots(Collection $users): array
    {
        return $users->map(function (ClientPortalUser $user) {
            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'login_key' => (string) $user->login_key,
                'email' => $user->email ? (string) $user->email : null,
                'portal_role' => $user->portal_role ? (string) $user->portal_role : null,
                'client_name' => $user->displayClientName(),
                'condominiums_label' => $user->portalCondominiumNames(),
                'active_device_count' => (int) ($user->active_device_count ?? 0),
            ];
        })->values()->all();
    }

    private function payloadJson(array $payload): array
    {
        return [
            'deep_link' => (string) ($payload['deep_link'] ?? ClientPortalPushCatalog::defaultDeepLink($payload['notification_type'] ?? null)),
            'screen' => (string) ($payload['screen'] ?? ClientPortalPushCatalog::defaultScreen($payload['notification_type'] ?? null)),
            'target_id' => filled($payload['target_id'] ?? null) ? (string) $payload['target_id'] : null,
        ];
    }
}
