<?php

namespace App\Jobs;

use App\Models\CalendarConnection;
use App\Services\Calendar\CalendarInboundSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCalendarWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param string $mode 'changes' (Google, via sync token) ou 'single' (Microsoft, id do evento)
     */
    public function __construct(
        public int $connectionId,
        public string $mode,
        public ?string $externalId = null,
    ) {
    }

    public function handle(CalendarInboundSyncService $service): void
    {
        try {
            $connection = CalendarConnection::query()->find($this->connectionId);
            if (!$connection || !$connection->is_active) {
                return;
            }

            if ($this->mode === 'single' && $this->externalId) {
                $service->processSingleEvent($connection, $this->externalId);

                return;
            }

            $service->processConnectionChanges($connection);
        } catch (\Throwable $e) {
            Log::warning('agenda.calendar.webhook_job_failed', [
                'connection_id' => $this->connectionId,
                'mode' => $this->mode,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
