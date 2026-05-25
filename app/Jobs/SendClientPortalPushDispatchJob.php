<?php

namespace App\Jobs;

use App\Models\ClientPortalDeviceToken;
use App\Models\ClientPortalNotification;
use App\Models\ClientPortalPushDispatch;
use App\Models\ClientPortalUser;
use App\Services\Mobile\FirebaseCloudMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendClientPortalPushDispatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $dispatchId,
    ) {
    }

    public function handle(FirebaseCloudMessagingService $fcmService): void
    {
        $dispatch = ClientPortalPushDispatch::query()->find($this->dispatchId);
        if (!$dispatch || !$fcmService->enabled()) {
            return;
        }

        if (!in_array((string) $dispatch->status, ['queued', 'processing'], true)) {
            return;
        }

        $dispatch->forceFill([
            'status' => 'processing',
            'processing_started_at' => $dispatch->processing_started_at ?: now(),
            'failure_reason' => null,
        ])->save();

        $recipientIds = collect((array) $dispatch->recipient_user_ids_json)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $users = ClientPortalUser::query()
            ->active()
            ->with([
                'deviceTokens' => fn ($query) => $query->active(),
            ])
            ->whereIn('id', $recipientIds->all())
            ->get()
            ->keyBy('id');

        $successCount = 0;
        $errorCount = 0;
        $invalidTokenCount = 0;
        $processedCount = 0;
        $totalRecipients = $recipientIds->count();

        foreach ($recipientIds as $recipientId) {
            $processedCount++;
            $portalUser = $users->get($recipientId);

            if (!$portalUser) {
                $errorCount++;
                $this->syncDispatchCounters($dispatch, $successCount, $errorCount, $invalidTokenCount, $processedCount, $totalRecipients);
                continue;
            }

            $notification = ClientPortalNotification::query()->create([
                'client_portal_user_id' => $portalUser->id,
                'client_condominium_id' => null,
                'type' => 'admin_push_' . $dispatch->notification_type,
                'title' => $dispatch->title,
                'body' => $dispatch->body,
                'data' => $this->notificationData($dispatch),
            ]);

            $devices = $portalUser->deviceTokens;
            if ($devices->isEmpty()) {
                $errorCount++;
                $notification->forceFill([
                    'failed_at' => now(),
                    'failure_reason' => 'Nenhum dispositivo ativo para envio de push.',
                ])->save();

                $this->syncDispatchCounters($dispatch, $successCount, $errorCount, $invalidTokenCount, $processedCount, $totalRecipients);
                continue;
            }

            $userDelivered = false;
            $userErrors = [];

            foreach ($devices as $device) {
                $result = $fcmService->sendToToken(
                    token: (string) $device->fcm_token,
                    notification: [
                        'title' => $dispatch->title,
                        'body' => $dispatch->body,
                    ],
                    data: array_merge($this->notificationData($dispatch), [
                        'title' => $dispatch->title,
                        'body' => $dispatch->body,
                        'notification_id' => (string) $notification->id,
                    ]),
                );

                if ($result['ok']) {
                    $userDelivered = true;
                    $device->forceFill(['last_seen_at' => now()])->save();
                    continue;
                }

                $userErrors[] = trim((string) ($result['message'] ?? 'Falha desconhecida no envio.'));

                if (!empty($result['invalid_token'])) {
                    $invalidTokenCount++;
                    $device->forceFill(['revoked_at' => now()])->save();
                }
            }

            if ($userDelivered) {
                $successCount++;
                $notification->forceFill([
                    'sent_at' => now(),
                    'failed_at' => null,
                    'failure_reason' => $userErrors !== [] ? implode(' | ', array_unique($userErrors)) : null,
                ])->save();
            } else {
                $errorCount++;
                $notification->forceFill([
                    'failed_at' => now(),
                    'failure_reason' => $userErrors !== [] ? implode(' | ', array_unique($userErrors)) : 'Falha ao enviar push para todos os dispositivos do usuario.',
                ])->save();
            }

            $this->syncDispatchCounters($dispatch, $successCount, $errorCount, $invalidTokenCount, $processedCount, $totalRecipients);
        }

        $dispatch->forceFill([
            'status' => $errorCount > 0
                ? ($successCount > 0 ? 'completed_with_errors' : 'failed')
                : 'completed',
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'invalid_token_count' => $invalidTokenCount,
            'finished_at' => now(),
            'failure_reason' => $errorCount > 0 && $successCount === 0 && !$dispatch->failure_reason
                ? 'O disparo terminou sem nenhum envio bem-sucedido.'
                : $dispatch->failure_reason,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $dispatch = ClientPortalPushDispatch::query()->find($this->dispatchId);
        if (!$dispatch) {
            return;
        }

        $dispatch->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'failure_reason' => $exception ? trim((string) $exception->getMessage()) : 'Falha inesperada ao processar o disparo de push.',
        ])->save();
    }

    private function notificationData(ClientPortalPushDispatch $dispatch): array
    {
        return [
            'dispatch_id' => (string) $dispatch->id,
            'notification_type' => (string) $dispatch->notification_type,
            'deep_link' => (string) data_get($dispatch->payload_json, 'deep_link', 'app://notifications'),
            'screen' => (string) data_get($dispatch->payload_json, 'screen', 'notifications'),
            'target_id' => (string) (data_get($dispatch->payload_json, 'target_id') ?? ''),
        ];
    }

    private function syncDispatchCounters(
        ClientPortalPushDispatch $dispatch,
        int $successCount,
        int $errorCount,
        int $invalidTokenCount,
        int $processedCount,
        int $totalRecipients,
    ): void {
        if ($processedCount !== $totalRecipients && ($processedCount % 10) !== 0) {
            return;
        }

        $dispatch->forceFill([
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'invalid_token_count' => $invalidTokenCount,
        ])->save();
    }
}
