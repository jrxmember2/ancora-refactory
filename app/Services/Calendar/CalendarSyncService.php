<?php

namespace App\Services\Calendar;

use App\Models\AgendaEvent;
use App\Models\AgendaEventSync;
use App\Models\CalendarConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CalendarSyncService
{
    public function __construct(private readonly CalendarProviders $providers)
    {
    }

    /**
     * Garante um access token valido para a conexao, renovando via refresh token se expirado.
     */
    public function freshAccessToken(CalendarConnection $connection): string
    {
        $provider = $this->providers->get($connection->provider);
        $token = (string) $connection->access_token;

        if ($provider && $connection->isExpired() && trim((string) $connection->refresh_token) !== '') {
            $refreshed = $provider->refreshToken((string) $connection->refresh_token);
            $token = (string) ($refreshed['access_token'] ?? $token);

            $connection->forceFill([
                'access_token' => $token,
                'token_expires_at' => now()->addSeconds((int) ($refreshed['expires_in'] ?? 3600) - 60),
            ]);
            if (!empty($refreshed['refresh_token'])) {
                $connection->forceFill(['refresh_token' => (string) $refreshed['refresh_token']]);
            }
            $connection->save();
        }

        return $token;
    }

    /**
     * Sincroniza um evento da agenda com os calendarios externos conectados do responsavel.
     * Nunca lanca excecao: falhas por conexao sao registradas e isoladas.
     */
    public function syncEvent(AgendaEvent $event, string $action = 'upsert'): void
    {
        if (!Schema::hasTable('calendar_connections') || !Schema::hasTable('agenda_event_syncs')) {
            return;
        }

        $userId = (int) ($event->responsible_user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $connections = CalendarConnection::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        foreach ($connections as $connection) {
            $provider = $this->providers->get($connection->provider);
            if (!$provider || !$provider->isConfigured()) {
                continue;
            }

            try {
                $this->syncToConnection($provider, $connection, $event, $action);
            } catch (\Throwable $e) {
                Log::warning('agenda.calendar.sync_failed', [
                    'event_id' => $event->id,
                    'connection_id' => $connection->id,
                    'provider' => $connection->provider,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function syncToConnection(
        CalendarProviderInterface $provider,
        CalendarConnection $connection,
        AgendaEvent $event,
        string $action
    ): void {
        $sync = AgendaEventSync::query()
            ->where('agenda_event_id', $event->id)
            ->where('connection_id', $connection->id)
            ->first();

        $token = $this->freshAccessToken($connection);

        if ($action === 'delete' || $event->status === 'cancelado' || $event->trashed()) {
            if ($sync) {
                $provider->deleteEvent($connection, $token, (string) $sync->external_event_id);
                // Mantem o mapeamento como "tombstone" (apontando para um evento
                // cancelado/na lixeira) em vez de apaga-lo. Assim, se o evento ainda
                // existir no calendario externo ou um webhook de importacao chegar
                // depois, o sync de entrada reconhece o external_event_id e nao recria
                // o compromisso na agenda (evitando que ele volte a aparecer no alerta).
                $sync->forceFill(['last_synced_at' => now()])->save();
            }

            return;
        }

        $externalId = $provider->pushEvent($connection, $token, $event, $sync?->external_event_id);

        AgendaEventSync::query()->updateOrCreate(
            ['agenda_event_id' => $event->id, 'connection_id' => $connection->id],
            [
                'provider' => $connection->provider,
                'external_event_id' => $externalId,
                'last_synced_at' => now(),
            ]
        );
    }
}
