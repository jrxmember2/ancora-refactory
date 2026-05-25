<?php

namespace App\Services\Hub;

use App\Jobs\SendHubPushNotificationJob;
use App\Models\HubDeviceToken;
use App\Models\HubNotification;
use App\Models\HubPushDispatch;
use App\Models\User;
use App\Services\Mobile\FirebaseCloudMessagingService;
use App\Support\Hub\HubApiPresenter;
use Illuminate\Support\Collection;

class HubPushNotificationService
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $fcmService,
    ) {
    }

    public function createForUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        ?string $type = 'notificacao_geral',
        ?string $module = 'hub',
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null,
    ): HubNotification {
        $notification = HubNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action_url' => $actionUrl,
            'data_json' => $data,
        ]);

        $this->queueDelivery($notification);

        return $notification;
    }

    /**
     * @param iterable<int, User> $users
     * @return Collection<int, HubNotification>
     */
    public function createForUsers(
        iterable $users,
        string $title,
        string $body,
        array $data = [],
        ?string $type = 'notificacao_geral',
        ?string $module = 'hub',
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null,
    ): Collection {
        return collect($users)
            ->filter(fn ($user) => $user instanceof User)
            ->map(fn (User $user) => $this->createForUser(
                user: $user,
                title: $title,
                body: $body,
                data: $data,
                type: $type,
                module: $module,
                entityType: $entityType,
                entityId: $entityId,
                actionUrl: $actionUrl,
            ))
            ->values();
    }

    public function queueDelivery(HubNotification $notification): void
    {
        if (!$this->fcmService->enabled()) {
            return;
        }

        SendHubPushNotificationJob::dispatch($notification->id)->afterCommit();
    }

    public function deliverNotificationById(int $notificationId): void
    {
        $notification = HubNotification::query()->find($notificationId);
        if (!$notification) {
            return;
        }

        $this->deliverNotification($notification);
    }

    public function deliverNotification(HubNotification $notification): void
    {
        if (!$this->fcmService->enabled()) {
            return;
        }

        $payload = $this->payloadFor($notification);
        $devices = HubDeviceToken::query()
            ->active()
            ->where('user_id', $notification->user_id)
            ->get();

        if ($devices->isEmpty()) {
            HubPushDispatch::query()->create([
                'hub_notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'title' => $notification->title,
                'body' => $notification->body,
                'data_json' => $payload,
                'status' => 'failed',
                'error_message' => 'Nenhum dispositivo ativo para envio de push.',
            ]);

            return;
        }

        foreach ($devices as $device) {
            $dispatch = HubPushDispatch::query()->create([
                'hub_notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'hub_device_token_id' => $device->id,
                'title' => $notification->title,
                'body' => $notification->body,
                'data_json' => $payload,
                'status' => 'pending',
            ]);

            $result = $this->fcmService->sendHubNotificationToToken(
                token: (string) $device->fcm_token,
                notification: [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                data: $payload,
            );

            if ($result['ok']) {
                $dispatch->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ])->save();

                $device->forceFill([
                    'last_seen_at' => now(),
                ])->save();

                continue;
            }

            $dispatch->forceFill([
                'status' => 'failed',
                'error_message' => trim((string) ($result['message'] ?? 'Falha ao enviar push.')),
            ])->save();

            if (!empty($result['invalid_token'])) {
                $device->forceFill([
                    'revoked_at' => now(),
                ])->save();
            }
        }
    }

    public function payloadFor(HubNotification $notification): array
    {
        return array_merge((array) ($notification->data_json ?? []), [
            'title' => $notification->title,
            'body' => $notification->body,
            'notification_id' => (string) $notification->id,
            'type' => (string) ($notification->type ?? ''),
            'module' => (string) ($notification->module ?? ''),
            'entity_type' => (string) ($notification->entity_type ?? ''),
            'entity_id' => $notification->entity_id ? (string) $notification->entity_id : '',
            'route' => HubApiPresenter::notificationRouteValue($notification) ?? '',
        ]);
    }
}
