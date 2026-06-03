<?php

namespace App\Jobs;

use App\Models\AgendaEvent;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncAgendaEventToCalendarsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $eventId,
        public string $action = 'upsert',
    ) {
    }

    public function handle(CalendarSyncService $sync): void
    {
        try {
            if (!Schema::hasTable('agenda_events')) {
                return;
            }

            $event = AgendaEvent::withTrashed()->find($this->eventId);
            if (!$event) {
                return;
            }

            $sync->syncEvent($event, $this->action);
        } catch (\Throwable $e) {
            Log::warning('agenda.calendar.sync_job_failed', [
                'event_id' => $this->eventId,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
