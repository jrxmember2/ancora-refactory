<?php

namespace App\Services\Calendar;

use App\Models\AgendaEvent;
use App\Models\CalendarConnection;

interface CalendarProviderInterface
{
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    /** URL de autorizacao OAuth para iniciar a conexao. */
    public function authorizationUrl(string $state, string $redirectUri): string;

    /**
     * Troca o code por tokens. Retorna:
     * ['access_token','refresh_token','expires_in','scope','account_email'].
     */
    public function exchangeCode(string $code, string $redirectUri): array;

    /** Renova o access token. Retorna ['access_token','refresh_token'?,'expires_in']. */
    public function refreshToken(string $refreshToken): array;

    /** Cria/atualiza o evento no calendario e retorna o id externo. */
    public function pushEvent(CalendarConnection $connection, string $accessToken, AgendaEvent $event, ?string $externalId): string;

    /** Remove o evento do calendario externo. */
    public function deleteEvent(CalendarConnection $connection, string $accessToken, string $externalId): void;
}
