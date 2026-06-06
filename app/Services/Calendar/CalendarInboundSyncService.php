<?php

namespace App\Services\Calendar;

use App\Models\AgendaEvent;
use App\Models\AgendaEventSync;
use App\Models\CalendarConnection;
use App\Models\CalendarSubscription;
use App\Services\Calendar\Contracts\CalendarWebhookProviderInterface;
use Illuminate\Support\Facades\DB;
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
            // Evento criado diretamente no calendario externo: importa como novo compromisso
            // apenas se a importacao estiver habilitada para o provedor (default desligado).
            if ($this->importEnabled($connection->provider) && empty($data['deleted'])) {
                return $this->importExternalEvent($connection, $externalId, $data);
            }

            return false; // por padrao, eventos externos nao sao importados
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

    private function importEnabled(string $provider): bool
    {
        return (bool) config('services.' . $provider . '_calendar.import_external', false);
    }

    private function importExternalEvent(CalendarConnection $connection, string $externalId, array $data): bool
    {
        if (empty($data['start_at'])) {
            return false;
        }

        // Idempotente: trava a faixa (connection, external) e reverifica dentro da transacao,
        // para que webhooks concorrentes nao importem o mesmo evento do Google mais de uma vez.
        return DB::transaction(function () use ($connection, $externalId, $data) {
            $already = AgendaEventSync::query()
                ->where('connection_id', $connection->id)
                ->where('external_event_id', $externalId)
                ->lockForUpdate()
                ->exists();

            if ($already) {
                return false;
            }

            // Mesmo evento do Google ja importado por OUTRA conexao (ex.: dois usuarios conectaram
            // o mesmo calendario). Reaproveita o compromisso existente e apenas mapeia esta conexao,
            // para nao duplicar o evento na agenda compartilhada.
            $existing = AgendaEventSync::query()
                ->where('provider', $connection->provider)
                ->where('external_event_id', $externalId)
                ->orderBy('agenda_event_id')
                ->first();

            if ($existing && AgendaEvent::withTrashed()->whereKey($existing->agenda_event_id)->exists()) {
                AgendaEventSync::query()->create([
                    'agenda_event_id' => $existing->agenda_event_id,
                    'connection_id' => $connection->id,
                    'provider' => $connection->provider,
                    'external_event_id' => $externalId,
                    'last_synced_at' => now(),
                ]);

                return false;
            }

            $event = AgendaEvent::query()->create([
                'title' => trim((string) ($data['title'] ?? '')) ?: 'Compromisso importado',
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'type' => 'compromisso',
                'status' => 'aberto',
                'all_day' => (bool) ($data['all_day'] ?? false),
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'] ?? null,
                'responsible_user_id' => $connection->user_id,
            ]);

            AgendaEventSync::query()->create([
                'agenda_event_id' => $event->id,
                'connection_id' => $connection->id,
                'provider' => $connection->provider,
                'external_event_id' => $externalId,
                'last_synced_at' => now(),
            ]);

            return true;
        });
    }

    private function webhookProvider(CalendarConnection $connection): ?CalendarWebhookProviderInterface
    {
        $provider = $this->providers->get($connection->provider);

        return $provider instanceof CalendarWebhookProviderInterface ? $provider : null;
    }
}
