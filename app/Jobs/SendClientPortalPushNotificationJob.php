<?php

namespace App\Jobs;

use App\Models\ClientPortalDeviceToken;
use App\Models\ClientPortalNotification;
use App\Services\Mobile\FirebaseCloudMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendClientPortalPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $notificationId,
    ) {
    }

    public function handle(FirebaseCloudMessagingService $fcmService): void
    {
        $notification = ClientPortalNotification::query()->find($this->notificationId);
        if (!$notification || !$fcmService->enabled()) {
            return;
        }

        $devices = ClientPortalDeviceToken::query()
            ->where('client_portal_user_id', $notification->client_portal_user_id)
            ->whereNull('revoked_at')
            ->get();

        if ($devices->isEmpty()) {
            $notification->forceFill([
                'failed_at' => $notification->failed_at ?: now(),
                'failure_reason' => 'Nenhum dispositivo ativo para envio de push.',
            ])->save();

            return;
        }

        $successCount = 0;
        $errors = [];

        foreach ($devices as $device) {
            $result = $fcmService->sendToToken(
                token: (string) $device->fcm_token,
                notification: [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                data: array_merge((array) $notification->data, [
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'notification_id' => (string) $notification->id,
                    'type' => (string) $notification->type,
                ]),
            );

            if ($result['ok']) {
                $successCount++;
                $device->forceFill(['last_seen_at' => now()])->save();
                continue;
            }

            $errors[] = trim((string) ($result['message'] ?? 'Falha desconhecida'));

            if (!empty($result['invalid_token'])) {
                $device->forceFill(['revoked_at' => now()])->save();
            }
        }

        $notification->forceFill([
            'sent_at' => $successCount > 0 ? now() : $notification->sent_at,
            'failed_at' => $successCount === 0 ? now() : null,
            'failure_reason' => $errors !== [] ? implode(' | ', array_unique($errors)) : null,
        ])->save();
    }
}
