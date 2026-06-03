<?php

namespace App\Services\Calendar;

use App\Models\AgendaEvent;
use App\Models\CalendarConnection;
use App\Services\Calendar\Contracts\CalendarWebhookProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MicrosoftCalendarProvider implements CalendarProviderInterface, CalendarWebhookProviderInterface
{
    private const SCOPES = 'offline_access openid email User.Read Calendars.ReadWrite';
    private const TIMEZONE = 'America/Sao_Paulo';

    public function key(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return 'Outlook / Microsoft 365';
    }

    public function isConfigured(): bool
    {
        return trim((string) config('services.microsoft_calendar.client_id')) !== ''
            && trim((string) config('services.microsoft_calendar.client_secret')) !== '';
    }

    private function tenant(): string
    {
        return trim((string) config('services.microsoft_calendar.tenant')) ?: 'common';
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $params = [
            'client_id' => (string) config('services.microsoft_calendar.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => self::SCOPES,
            'state' => $state,
        ];

        return 'https://login.microsoftonline.com/' . $this->tenant() . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post(
            'https://login.microsoftonline.com/' . $this->tenant() . '/oauth2/v2.0/token',
            [
                'code' => $code,
                'client_id' => (string) config('services.microsoft_calendar.client_id'),
                'client_secret' => (string) config('services.microsoft_calendar.client_secret'),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'scope' => self::SCOPES,
            ]
        )->throw()->json();

        $accessToken = (string) ($response['access_token'] ?? '');
        $email = '';
        try {
            $me = Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me')->json();
            $email = (string) ($me['mail'] ?? $me['userPrincipalName'] ?? '');
        } catch (\Throwable) {
            // email é opcional
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
        $response = Http::asForm()->post(
            'https://login.microsoftonline.com/' . $this->tenant() . '/oauth2/v2.0/token',
            [
                'refresh_token' => $refreshToken,
                'client_id' => (string) config('services.microsoft_calendar.client_id'),
                'client_secret' => (string) config('services.microsoft_calendar.client_secret'),
                'grant_type' => 'refresh_token',
                'scope' => self::SCOPES,
            ]
        )->throw()->json();

        return [
            'access_token' => (string) ($response['access_token'] ?? ''),
            'refresh_token' => (string) ($response['refresh_token'] ?? ''),
            'expires_in' => (int) ($response['expires_in'] ?? 3600),
        ];
    }

    public function pushEvent(CalendarConnection $connection, string $accessToken, AgendaEvent $event, ?string $externalId): string
    {
        $body = $this->payload($event);

        if ($externalId) {
            $response = Http::withToken($accessToken)->patch('https://graph.microsoft.com/v1.0/me/events/' . rawurlencode($externalId), $body);
            if ($response->status() === 404) {
                $response = Http::withToken($accessToken)->post('https://graph.microsoft.com/v1.0/me/events', $body);
            }
        } else {
            $response = Http::withToken($accessToken)->post('https://graph.microsoft.com/v1.0/me/events', $body);
        }

        $id = (string) ($response->throw()->json('id') ?? '');
        if ($id === '') {
            throw new RuntimeException('Microsoft Graph nao retornou o id do evento.');
        }

        return $id;
    }

    public function deleteEvent(CalendarConnection $connection, string $accessToken, string $externalId): void
    {
        $response = Http::withToken($accessToken)->delete('https://graph.microsoft.com/v1.0/me/events/' . rawurlencode($externalId));

        if (!$response->successful() && $response->status() !== 404) {
            $response->throw();
        }
    }

    public function webhooksEnabled(): bool
    {
        return $this->isConfigured() && (bool) config('services.microsoft_calendar.webhooks_enabled');
    }

    public function createSubscription(string $accessToken, string $notificationUrl, string $clientState): array
    {
        // Microsoft exige expiracao curta para eventos (~3 dias). Usamos ~2 dias.
        $expires = now()->addMinutes(2880);

        $response = Http::withToken($accessToken)->post('https://graph.microsoft.com/v1.0/subscriptions', [
            'changeType' => 'updated,deleted',
            'notificationUrl' => $notificationUrl,
            'resource' => 'me/events',
            'expirationDateTime' => $expires->toIso8601String(),
            'clientState' => $clientState,
        ])->throw()->json();

        return [
            'subscription_id' => (string) ($response['id'] ?? ''),
            'client_state' => $clientState,
            'expires_at' => isset($response['expirationDateTime']) ? Carbon::parse((string) $response['expirationDateTime']) : $expires,
        ];
    }

    public function deleteSubscription(string $accessToken, string $subscriptionId, ?string $resourceId = null): void
    {
        $response = Http::withToken($accessToken)->delete('https://graph.microsoft.com/v1.0/subscriptions/' . rawurlencode($subscriptionId));

        if (!$response->successful() && $response->status() !== 404) {
            $response->throw();
        }
    }

    public function fetchEvent(string $accessToken, string $externalId): ?array
    {
        $response = Http::withToken($accessToken)->get('https://graph.microsoft.com/v1.0/me/events/' . rawurlencode($externalId));

        if ($response->status() === 404) {
            return ['deleted' => true];
        }

        $event = $response->throw()->json();

        return [
            'deleted' => (bool) ($event['isCancelled'] ?? false),
            'title' => (string) ($event['subject'] ?? ''),
            'description' => (string) ($event['bodyPreview'] ?? ''),
            'location' => (string) ($event['location']['displayName'] ?? ''),
            'all_day' => (bool) ($event['isAllDay'] ?? false),
            'start_at' => $event['start']['dateTime'] ?? null,
            'end_at' => $event['end']['dateTime'] ?? null,
        ];
    }

    public function listChanges(string $accessToken, ?string $syncToken): array
    {
        // Microsoft Graph entrega o id do evento na propria notificacao (processSingleEvent),
        // portanto nao usamos o modelo de sync token incremental aqui.
        return ['changes' => [], 'sync_token' => $syncToken];
    }

    private function payload(AgendaEvent $event): array
    {
        $start = $event->start_at instanceof Carbon ? $event->start_at->copy() : Carbon::parse((string) $event->start_at);
        $subject = ($event->is_fatal ? '[PRAZO FATAL] ' : '') . (string) $event->title;

        if ($event->all_day) {
            $end = $event->end_at instanceof Carbon ? $event->end_at->copy() : $start->copy()->addDay();

            return [
                'subject' => $subject,
                'body' => ['contentType' => 'text', 'content' => (string) ($event->description ?? '')],
                'location' => ['displayName' => (string) ($event->location ?? '')],
                'isAllDay' => true,
                'start' => ['dateTime' => $start->copy()->startOfDay()->format('Y-m-d\T00:00:00'), 'timeZone' => self::TIMEZONE],
                'end' => ['dateTime' => $end->copy()->startOfDay()->format('Y-m-d\T00:00:00'), 'timeZone' => self::TIMEZONE],
            ];
        }

        $end = $event->end_at instanceof Carbon ? $event->end_at->copy() : $start->copy()->addHour();

        return [
            'subject' => $subject,
            'body' => ['contentType' => 'text', 'content' => (string) ($event->description ?? '')],
            'location' => ['displayName' => (string) ($event->location ?? '')],
            'start' => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => self::TIMEZONE],
            'end' => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => self::TIMEZONE],
        ];
    }
}
