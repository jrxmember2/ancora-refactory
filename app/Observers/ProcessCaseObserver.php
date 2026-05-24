<?php

namespace App\Observers;

use App\Models\ProcessCase;
use App\Services\Hub\HubNotificationService;
use App\Services\Mobile\ClientPortalNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ProcessCaseObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ClientPortalNotificationService $notifications,
        private readonly HubNotificationService $hubNotifications,
    ) {
    }

    public function updated(ProcessCase $case): void
    {
        if (!$case->wasChanged('status_option_id')) {
            return;
        }

        $case->loadMissing('statusOption');
        $this->notifications->notifyProcessStatusChanged($case);
        $this->hubNotifications->notifyProcessStatusChanged($case);
    }
}
