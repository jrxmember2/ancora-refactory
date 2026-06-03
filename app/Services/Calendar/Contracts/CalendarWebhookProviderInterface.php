<?php

namespace App\Services\Calendar\Contracts;

interface CalendarWebhookProviderInterface
{
    public function webhooksEnabled(): bool;

    /**
     * Cria a inscricao de notificacoes (watch/subscription).
     * @return array{subscription_id:string,resource_id?:string,client_state:string,expires_at:?\Illuminate\Support\Carbon,sync_token?:string}
     */
    public function createSubscription(string $accessToken, string $notificationUrl, string $clientState): array;

    public function deleteSubscription(string $accessToken, string $subscriptionId, ?string $resourceId = null): void;

    /**
     * Busca um evento por id. Retorna dados normalizados ou null se nao encontrado.
     * @return array{deleted?:bool,title?:string,description?:string,location?:string,start_at?:mixed,end_at?:mixed,all_day?:bool}|null
     */
    public function fetchEvent(string $accessToken, string $externalId): ?array;

    /**
     * Lista mudancas incrementais desde o sync token (modelo do Google).
     * @return array{changes:array<int,array>,sync_token:?string}
     */
    public function listChanges(string $accessToken, ?string $syncToken): array;
}
