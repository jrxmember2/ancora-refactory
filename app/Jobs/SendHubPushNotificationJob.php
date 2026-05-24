<?php

namespace App\Jobs;

use App\Models\HubDeviceToken;
use App\Models\HubNotification;
use App\Models\HubPushDispatch;
use App\Services\Mobile\FirebaseCloudMessagingService;
use App\Support\Hub\HubApiPresenter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendHubPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $notificationId,
    ) {
    }

    public function handle(FirebaseCloudMessagingService $fcmService): void
    {
        $notification = HubNotification::query()->find($this->notificationId);
        if (!$notification || !$fcmService->enabled()) {
            return;
        }

        $payload = array_merge((array) ($notification->data_json ?? []), [
            'title' => $notification->title,
            'body' => $notification->body,
            'notification_id' => (string) $notification->id,
            'type' => (string) ($notification->type ?? ''),
            'module' => (string) ($notification->module ?? ''),
            'entity_type' => (string) ($notification->entity_type ?? ''),
            'entity_id' => $notification->entity_id ? (string) $notification->entity_id : '',
            'route' => HubApiPresenter::notificationRouteValue($notification) ?? '',
        ]);

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

            $result = $fcmService->sendHubNotificationToToken(
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
}
