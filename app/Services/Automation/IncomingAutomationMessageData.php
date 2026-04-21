<?php

namespace App\Services\Automation;

use App\Support\Automation\AutomationText;

final class IncomingAutomationMessageData
{
    public function __construct(
        public readonly string $channel,
        public readonly string $provider,
        public readonly string $phone,
        public readonly ?string $externalContactId,
        public readonly ?string $externalMessageId,
        public readonly string $messageText,
        public readonly ?string $timestamp,
        public readonly array $metadata,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            channel: trim((string) ($data['channel'] ?? 'whatsapp')) ?: 'whatsapp',
            provider: trim((string) ($data['provider'] ?? 'evolution')) ?: 'evolution',
            phone: AutomationText::digits((string) ($data['phone'] ?? '')),
            externalContactId: self::nullableString($data['external_contact_id'] ?? null),
            externalMessageId: self::nullableString($data['external_message_id'] ?? null),
            messageText: trim((string) ($data['message_text'] ?? '')),
            timestamp: self::nullableString($data['timestamp'] ?? null),
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    public function toPayload(): array
    {
        return [
            'channel' => $this->channel,
            'provider' => $this->provider,
            'phone' => $this->phone,
            'external_contact_id' => $this->externalContactId,
            'external_message_id' => $this->externalMessageId,
            'message_text' => $this->messageText,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
