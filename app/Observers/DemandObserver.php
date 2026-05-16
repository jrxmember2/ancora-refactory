<?php

namespace App\Observers;

use App\Models\Demand;
use App\Services\Mobile\ClientPortalNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DemandObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly ClientPortalNotificationService $notifications,
    ) {
    }

    public function created(Demand $demand): void
    {
        $demand->loadMissing(['tag', 'category', 'condominium']);
        $this->notifications->notifyDemandCreated($demand);
    }

    public function updated(Demand $demand): void
    {
        if (!$demand->wasChanged('status')) {
            return;
        }

        $demand->loadMissing(['tag', 'category', 'condominium']);
        $this->notifications->notifyDemandStatusChanged($demand, (string) $demand->getOriginal('status'));
    }
}
