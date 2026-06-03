<?php

namespace App\Services\Calendar;

use App\Models\AgendaEvent;
use App\Models\CalendarConnection;
use App\Services\Calendar\Contracts\CalendarWebhookProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleCalendarProvider implements CalendarProviderInterface, CalendarWebhookProviderInterface
{
    private const SCOPES = 'openid email https://www.googleapis.com/auth/calendar.events';
    private const TIMEZONE = 'America/Sao_Paulo';

    public function key(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Agenda';
    }

    public function isConfigured(): bool
    {
        return trim((string) config('services.google_calendar.client_id')) !== ''
            && trim((string) config('services.google_calendar.client_secret')) !== '';
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $params = [
            'client_id' => (string) config('services.google_calendar.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => (string) config('services.google_calendar.client_id'),
            'client_secret' => (string) config('services.google_calendar.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        $accessToken = (string) ($response['access_token'] ?? '');
        $email = '';
        try {
            $email = (string) (Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo')
                ->json('email') ?? '');
        } catch (\Throwable) {
            // email é opcional para o funcionamento
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => (string) ($response['refresh_token'] ?? ''),
            'expires_in' => (int) ($response['expires_in'] ?? 3600),
            'scope' => (string) ($response['scope'] ?? self::SCOPES),
            'account_email' => $email,
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'refresh_token' => $refreshToken,
            'client_id' => (string) config('services.google_calendar.client_id'),
            'client_secret' => (string) config('services.google_calendar.client_secret'),
            'grant_type' => 'refresh_token',
        ])->throw()->json();

        return [
            'access_token' => (string) ($response['access_token'] ?? ''),
            'expires_in' => (int) ($response['expires_in'] ?? 3600),
        ];
    }

    public function pushEvent(CalendarConnection $connection, string $accessToken, AgendaEvent $event, ?string $externalId): string
    {
        $calendar = trim((string) ($connection->calendar_id ?? '')) ?: 'primary';
        $base = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendar) . '/events';
        $body = $this->payload($event);

        if ($externalId) {
            $response = Http::withToken($accessToken)->put($base . '/' . rawurlencode($externalId), $body);
            if ($response->status() === 404) {
                $response = Http::withToken($accessToken)->post($base, $body);
            }
        } else {
            $response = Http::withToken($accessToken)->post($base, $body);
        }

        $id = (string) ($response->throw()->json('id') ?? '');
        if ($id === '') {
            throw new RuntimeException('Google nao retornou o id do evento.');
        }

        return $id;
    }

    public function deleteEvent(CalendarConnection $connection, string $accessToken, string $externalId): void
    {
        $calendar = trim((string) ($connection->calendar_id ?? '')) ?: 'primary';
        $response = Http::withToken($accessToken)->delete(
            'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendar) . '/events/' . rawurlencode($externalId)
        );

        if (!$response->successful() && $response->status() !== 404 && $response->status() !== 410) {
            $response->throw();
        }
    }

    public function webhooksEnabled(): bool
    {
        return $this->isConfigured() && (bool) config('services.google_calendar.webhooks_enabled');
    }

    public function createSubscription(string $accessToken, string $notificationUrl, string $clientState): array
    {
        $channelId = (string) Str::uuid();
        $response = Http::withToken($accessToken)->post(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/watch',
            [
                'id' => $channelId,
                'type' => 'web_hook',
                'address' => $notificationUrl,
                'token' => $clientState,
            ]
        )->throw()->json();

        $expiration = isset($response['expiration']) ? Carbon::createFromTimestampMs((int) $response['expiration']) : null;

        return [
            'subscription_id' => $channelId,
            'resource_id' => (string) ($response['resourceId'] ?? ''),
            'client_state' => $clientState,
            'expires_at' => $expiration,
        ];
    }

    public function deleteSubscription(string $accessToken, string $subscriptionId, ?string $resourceId = null): void
    {
        $response = Http::withToken($accessToken)->post('https://www.googleapis.com/calendar/v3/channels/stop', [
            'id' => $subscriptionId,
            'resourceId' => (string) $resourceId,
        ]);

        if (!$response->successful() && $response->status() !== 404) {
            $response->throw();
        }
    }

    public function fetchEvent(string $accessToken, string $externalId): ?array
    {
        $response = Http::withToken($accessToken)->get(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode($externalId)
        );

        if ($response->status() === 404 || $response->status() === 410) {
            return ['deleted' => true];
        }

        return $this->normalize($response->throw()->json());
    }

    public function listChanges(string $accessToken, ?string $syncToken): array
    {
        $changes = [];
        $params = $syncToken ? ['syncToken' => $syncToken] : ['timeMin' => now()->subDays(30)->toRfc3339String()];
        $nextSync = $syncToken;
        $pageToken = null;
        $guard = 0;

        do {
            $query = $params;
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', $query);
            if ($response->status() === 410) {
                // sync token invalido: reinicia sem token na proxima rodada
                return ['changes' => [], 'sync_token' => null];
            }
            $json = $response->throw()->json();

            foreach (($json['items'] ?? []) as $item) {
                $normalized = $this->normalize($item);
                $normalized['external_event_id'] = (string) ($item['id'] ?? '');
                $changes[] = $normalized;
            }

            $pageToken = $json['nextPageToken'] ?? null;
            $nextSync = $json['nextSyncToken'] ?? $nextSync;
            $guard++;
        } while ($pageToken && $guard < 20);

        return ['changes' => $changes, 'sync_token' => $nextSync];
    }

    private function normalize(array $event): array
    {
        $allDay = isset($event['start']['date']);

        return [
            'deleted' => ($event['status'] ?? '') === 'cancelled',
            'title' => (string) ($event['summary'] ?? ''),
            'description' => (string) ($event['description'] ?? ''),
            'location' => (string) ($event['location'] ?? ''),
            'all_day' => $allDay,
            'start_at' => $event['start']['dateTime'] ?? ($event['start']['date'] ?? null),
            'end_at' => $event['end']['dateTime'] ?? ($event['end']['date'] ?? null),
        ];
    }

    private function payload(AgendaEvent $event): array
    {
        $start = $event->start_at instanceof Carbon ? $event->start_at->copy() : Carbon::parse((string) $event->start_at);
        $summary = ($event->is_fatal ? '[PRAZO FATAL] ' : '') . (string) $event->title;

        $payload = [
            'summary' => $summary,
            'description' => (string) ($event->description ?? ''),
            'location' => (string) ($event->location ?? ''),
            'status' => $event->status === 'cancelado' ? 'cancelled' : 'confirmed',
        ];

        if ($event->all_day) {
            $end = ($event->end_at instanceof Carbon ? $event->end_at->copy() : $start->copy())->addDay();
            $payload['start'] = ['date' => $start->format('Y-m-d')];
            $payload['end'] = ['date' => $end->format('Y-m-d')];
        } else {
            $end = $event->end_at instanceof Carbon ? $event->end_at->copy() : $start->copy()->addHour();
            $payload['start'] = ['dateTime' => $start->toRfc3339String(), 'timeZone' => self::TIMEZONE];
            $payload['end'] = ['dateTime' => $end->toRfc3339String(), 'timeZone' => self::TIMEZONE];
        }

        return $payload;
    }
}
