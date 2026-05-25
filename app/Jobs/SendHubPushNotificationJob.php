<?php

namespace App\Jobs;

use App\Services\Hub\HubPushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendHubPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $notificationId,
    ) {
    }

    public function handle(HubPushNotificationService $pushNotifications): void
    {
        $pushNotifications->deliverNotificationById($this->notificationId);
    }
}
