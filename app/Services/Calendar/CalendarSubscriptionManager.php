<?php

namespace App\Services\Calendar;

use App\Models\CalendarConnection;
use App\Models\CalendarSubscription;
use App\Services\Calendar\Contracts\CalendarWebhookProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CalendarSubscriptionManager
{
    public function __construct(
        private readonly CalendarProviders $providers,
        private readonly CalendarSyncService $syncService,
    ) {
    }

    /**
     * Cria/renova a inscricao de webhooks para a conexao, se o provedor suportar e estiver
     * habilitado. Nunca lanca excecao.
     */
    public function ensureSubscription(CalendarConnection $connection): void
    {
        if (!Schema::hasTable('calendar_subscriptions')) {
            return;
        }

        $provider = $this->providers->get($connection->provider);
        if (!$provider instanceof CalendarWebhookProviderInterface || !$provider->webhooksEnabled()) {
            return;
        }

        try {
            $this->removeSubscription($connection); // evita duplicidade
            $clientState = Str::random(40);
            $token = $this->syncService->freshAccessToken($connection);
            $result = $provider->createSubscription($token, $this->notificationUrl($connection->provider), $clientState);

            CalendarSubscription::query()->create([
                'connection_id' => $connection->id,
                'provider' => $connection->provider,
                'subscription_id' => (string) ($result['subscription_id'] ?? ''),
                'resource_id' => $result['resource_id'] ?? null,
                'client_state' => $result['client_state'] ?? $clientState,
                'sync_token' => $result['sync_token'] ?? null,
                'expires_at' => $result['expires_at'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('agenda.calendar.subscription_create_failed', [
                'connection_id' => $connection->id,
                'provider' => $connection->provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function removeSubscription(CalendarConnection $connection): void
    {
        if (!Schema::hasTable('calendar_subscriptions')) {
            return;
        }

        $subscription = CalendarSubscription::query()->where('connection_id', $connection->id)->first();
        if (!$subscription) {
            return;
        }

        $provider = $this->providers->get($connection->provider);
        if ($provider instanceof CalendarWebhookProviderInterface) {
            try {
                $token = $this->syncService->freshAccessToken($connection);
                $provider->deleteSubscription($token, (string) $subscription->subscription_id, $subscription->resource_id);
            } catch (\Throwable $e) {
                Log::warning('agenda.calendar.subscription_delete_failed', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $subscription->delete();
    }

    /**
     * Renova as inscricoes proximas de expirar. Retorna quantas foram renovadas.
     */
    public function renewExpiring(): int
    {
        if (!Schema::hasTable('calendar_subscriptions')) {
            return 0;
        }

        $renewed = 0;
        $subscriptions = CalendarSubscription::query()->with('connection')->get();

        foreach ($subscriptions as $subscription) {
            if (!$subscription->isExpiringSoon() || !$subscription->connection) {
                continue;
            }

            $this->ensureSubscription($subscription->connection);
            $renewed++;
        }

        return $renewed;
    }

    private function notificationUrl(string $provider): string
    {
        return route('agenda.webhooks.' . $provider);
    }
}
