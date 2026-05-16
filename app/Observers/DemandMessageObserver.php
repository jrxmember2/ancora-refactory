<?php

namespace App\Observers;

use App\Models\DemandMessage;
use App\Services\Mobile\ClientPortalNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DemandMessageObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ClientPortalNotificationService $notifications,
    ) {
    }

    public function created(DemandMessage $message): void
    {
        $this->notifications->notifyDemandReply($message);
    }
}
