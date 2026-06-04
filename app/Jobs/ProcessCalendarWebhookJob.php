<?php

namespace App\Jobs;

use App\Models\CalendarConnection;
use App\Services\Calendar\CalendarInboundSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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

    /**
     * Serializa o processamento por conexao: o Google dispara varios webhooks quase
     * simultaneos e, sem trava, cada job faria um listChanges completo antes de existir o
     * mapeamento, importando os mesmos eventos varias vezes (duplicacao).
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        // Sobreposicao e descartada (dontRelease): o job que ja esta rodando faz o listChanges
        // completo e cobre as mesmas mudancas; a proxima notificacao reescaneia pelo syncToken.
        // Funciona em qualquer driver de fila (inclusive sync) sem reenfileirar/loopar.
        return [
            (new WithoutOverlapping('calendar-webhook-' . $this->connectionId))
                ->dontRelease()
                ->expireAfter(180),
        ];
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
