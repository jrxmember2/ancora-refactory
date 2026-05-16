<?php

namespace App\Observers;

use App\Models\ProcessCasePhase;
use App\Services\Mobile\ClientPortalNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ProcessCasePhaseObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ClientPortalNotificationService $notifications,
    ) {
    }

    public function created(ProcessCasePhase $phase): void
    {
        $phase->loadMissing('processCase.statusOption');
        $this->notifications->notifyProcessPhaseCreated($phase);
    }
}
