<?php

namespace App\Support\Hub;

use App\Models\AiOfficeChatConversation;
use App\Models\AiOfficeChatMessage;
use App\Models\ClientCondominium;
use App\Services\Ai\OfficeAiChatService;

class HubLemePresenter
{
    public static function availability(
        bool $configured,
        bool $aiEnabled,
        ?string $message = null,
    ): array {
        return [
            'configured' => $configured,
            'ai_enabled' => $aiEnabled,
            'can_chat' => $configured && $aiEnabled,
            'message' => $message ? trim($message) : null,
        ];
    }

    public static function scopeOptions(bool $condominiumEnabled = true): array
    {
        return [
            [
                'key' => 'general',
                'label' => 'Geral',
                'supported' => true,
                'requires_reference' => false,
                'reference_type' => null,
                'description' => 'Consulta a base geral do escritório.',
            ],
            [
                'key' => 'condominium',
                'label' => 'Condomínio',
                'supported' => $condominiumEnabled,
                'requires_reference' => true,
                'reference_type' => 'condominium',
                'description' => $condominiumEnabled
                    ? 'Consulta documentos e referências de um condomínio específico.'
                    : 'Ainda não há condomínios disponíveis para este escopo.',
            ],
            [
                'key' => 'client',
                'label' => 'Cliente',
                'supported' => false,
                'requires_reference' => true,
                'reference_type' => 'client',
                'description' => 'Disponível em breve.',
            ],
            [
                'key' => 'process',
                'label' => 'Processo',
                'supported' => false,
                'requires_reference' => true,
                'reference_type' => 'process',
                'description' => 'Disponível em breve.',
            ],
            [
                'key' => 'collection',
                'label' => 'Cobrança',
                'supported' => false,
                'requires_reference' => true,
                'reference_type' => 'collection',
                'description' => 'Disponível em breve.',
            ],
        ];
    }

    public static function condominiumOption(ClientCondominium $condominium): array
    {
        return [
            'id' => (int) $condominium->id,
            'label' => (string) $condominium->name,
        ];
    }

    public static function conversationSummary(AiOfficeChatConversation $conversation): array
    {
        return [
            'id' => (int) $conversation->id,
            'title' => (string) $conversation->displayTitle(),
            'scope_key' => self::scopeKey((string) $conversation->scope_type),
            'scope_label' => (string) $conversation->scopeLabel(),
            'client_condominium_id' => $conversation->client_condominium_id ? (int) $conversation->client_condominium_id : null,
            'messages_count' => (int) ($conversation->messages_count ?? 0),
            'last_message_at' => $conversation->last_message_at?->toAtomString(),
            'last_message_at_br' => $conversation->last_message_at?->format('d/m/Y H:i'),
            'created_at' => $conversation->created_at?->toAtomString(),
            'created_at_br' => $conversation->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function conversationDetail(AiOfficeChatConversation $conversation): array
    {
        return array_merge(self::conversationSummary($conversation), [
            'messages' => $conversation->relationLoaded('messages')
                ? $conversation->messages->map(fn (AiOfficeChatMessage $message) => self::message($message))->values()->all()
                : [],
            'available_actions' => [
                'can_delete' => trim((string) $conversation->status) !== 'deleted',
            ],
        ]);
    }

    public static function message(AiOfficeChatMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'role' => (string) $message->role,
            'content' => (string) $message->content,
            'status' => (string) ($message->status ?: 'success'),
            'provider' => $message->provider ? (string) $message->provider : null,
            'model' => $message->model ? (string) $message->model : null,
            'error_message' => $message->error_message ? (string) $message->error_message : null,
            'source_chunks_count' => $message->source_chunks_count !== null ? (int) $message->source_chunks_count : null,
            'documents' => collect((array) data_get($message->meta_json, 'documents', []))
                ->map(fn ($document) => self::document($document))
                ->values()
                ->all(),
            'created_at' => $message->created_at?->toAtomString(),
            'created_at_br' => $message->created_at?->format('d/m/Y H:i'),
            'can_copy' => (string) $message->role === 'assistant',
        ];
    }

    public static function document(mixed $document): array
    {
        $payload = is_array($document) ? $document : [];

        return [
            'document_kind' => self::nullableString($payload['document_kind'] ?? null),
            'document_kind_label' => self::nullableString($payload['document_kind_label'] ?? null),
            'title' => self::nullableString($payload['title'] ?? null),
            'source' => self::nullableString($payload['source'] ?? null),
            'document_type' => self::nullableString($payload['document_type'] ?? null),
        ];
    }

    public static function scopeKey(string $scopeType): string
    {
        return $scopeType === OfficeAiChatService::SCOPE_CONDOMINIUM
            ? 'condominium'
            : 'general';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }
}
