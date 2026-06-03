<?php

namespace App\Services\Calendar;

use App\Models\AgendaEvent;
use App\Models\AgendaEventSync;
use App\Models\CalendarConnection;
use App\Models\CalendarSubscription;
use App\Services\Calendar\Contracts\CalendarWebhookProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CalendarInboundSyncService
{
    public function __construct(
        private readonly CalendarProviders $providers,
        private readonly CalendarSyncService $syncService,
    ) {
    }

    /**
     * Aplica uma mudanca externa a um compromisso que o Ancora criou (mapeado em
     * agenda_event_syncs). Nunca cria eventos novos a partir do calendario externo e nunca
     * exclui de forma destrutiva: remocao externa marca o evento como "cancelado".
     *
     * @param array{deleted?:bool,title?:string,description?:string,location?:string,start_at?:mixed,end_at?:mixed,all_day?:bool} $data
     */
    public function applyOwnedEvent(CalendarConnection $connection, string $externalId, array $data): bool
    {
        if (!Schema::hasTable('agenda_event_syncs') || !Schema::hasTable('agenda_events')) {
            return false;
        }

        $sync = AgendaEventSync::query()
            ->where('connection_id', $connection->id)
            ->where('external_event_id', $externalId)
            ->first();

        if (!$sync) {
            return false; // evento nao pertence ao Ancora -> ignorado de proposito
        }

        $event = AgendaEvent::withTrashed()->find($sync->agenda_event_id);
        if (!$event) {
            return false;
        }

        if (!empty($data['deleted'])) {
            if ($event->status !== 'cancelado') {
                $event->forceFill(['status' => 'cancelado'])->save();
            }

            return true;
        }

        $changes = [];
        foreach (['title', 'description', 'location'] as $key) {
            if (array_key_exists($key, $data)) {
                $changes[$key] = $data[$key];
            }
        }
        if (array_key_exists('all_day', $data)) {
            $changes['all_day'] = (bool) $data['all_day'];
        }
        if (!empty($data['start_at'])) {
            $changes['start_at'] = $data['start_at'];
        }
        if (array_key_exists('end_at', $data)) {
            $changes['end_at'] = $data['end_at'] ?: null;
        }

        if ($changes !== []) {
            $event->forceFill($changes)->save();
        }

        $sync->forceFill(['last_synced_at' => now()])->save();

        return true;
    }

    /**
     * Microsoft Graph: a notificacao traz o id do evento. Busca e aplica.
     */
    public function processSingleEvent(CalendarConnection $connection, string $externalId): void
    {
        $provider = $this->webhookProvider($connection);
        if (!$provider) {
            return;
        }

        try {
            $token = $this->syncService->freshAccessToken($connection);
            $data = $provider->fetchEvent($token, $externalId);
            if ($data !== null) {
                $this->applyOwnedEvent($connection, $externalId, $data);
            }
        } catch (\Throwable $e) {
            Log::warning('agenda.calendar.inbound_single_failed', [
                'connection_id' => $connection->id,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Google: a notificacao apenas avisa que algo mudou no canal. Busca as mudancas
     * incrementais via sync token e aplica cada uma aos eventos que o Ancora possui.
     */
    public function processConnectionChanges(CalendarConnection $connection): void
    {
        $provider = $this->webhookProvider($connection);
        if (!$provider) {
            return;
        }

        $subscription = CalendarSubscription::query()->where('connection_id', $connection->id)->first();

        try {
            $token = $this->syncService->freshAccessToken($connection);
            $result = $provider->listChanges($token, $subscription?->sync_token);

            foreach (($result['changes'] ?? []) as $change) {
                $externalId = (string) ($change['external_event_id'] ?? '');
                if ($externalId !== '') {
                    $this->applyOwnedEvent($connection, $externalId, $change);
                }
            }

            if ($subscription && !empty($result['sync_token'])) {
                $subscription->forceFill(['sync_token' => (string) $result['sync_token']])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('agenda.calendar.inbound_changes_failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function webhookProvider(CalendarConnection $connection): ?CalendarWebhookProviderInterface
    {
        $provider = $this->providers->get($connection->provider);

        return $provider instanceof CalendarWebhookProviderInterface ? $provider : null;
    }
}
