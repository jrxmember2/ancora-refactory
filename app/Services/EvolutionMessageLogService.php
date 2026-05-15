<?php

namespace App\Services;

use App\Models\AutomationSession;
use App\Models\EvolutionMessageLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class EvolutionMessageLogService
{
    private ?bool $tablesReady = null;

    public function recordInbound(string $module, array $message, array $context = []): array
    {
        if (!$this->tablesReady()) {
            return [
                'log' => new EvolutionMessageLog([
                    'provider' => trim((string) ($message['provider'] ?? 'evolution')) ?: 'evolution',
                    'module' => $module,
                    'direction' => 'inbound',
                    'status' => $this->nullableString($message['status'] ?? null) ?: 'received',
                    'phone' => $this->normalizePhone((string) ($message['phone'] ?? '')),
                    'remote_jid' => $this->normalizeRemoteJid((string) ($message['remote_jid'] ?? '')),
                    'body_text' => $this->nullableString($message['body_text'] ?? null),
                ]),
                'was_created' => false,
                'already_handled' => false,
            ];
        }

        $provider = trim((string) ($message['provider'] ?? 'evolution')) ?: 'evolution';
        $externalMessageId = trim((string) ($message['external_message_id'] ?? ''));

        $existing = null;
        if ($externalMessageId !== '') {
            $existing = EvolutionMessageLog::query()
                ->where('provider', $provider)
                ->where('external_message_id', $externalMessageId)
                ->first();
        }

        $metadata = $this->mergeMetadata(
            $existing?->metadata,
            $context['metadata'] ?? [],
            [
                'context' => $this->onlyContextKeys($context),
                'last_webhook_payload' => $message['payload'] ?? null,
            ]
        );

        if ($existing) {
            $existing->update([
                'module' => $existing->module ?: $module,
                'direction' => 'inbound',
                'status' => $existing->status ?: 'received',
                'message_type' => $existing->message_type ?: $this->nullableString($message['message_type'] ?? null),
                'external_contact_id' => $existing->external_contact_id ?: $this->nullableString($message['external_contact_id'] ?? null),
                'phone' => $existing->phone ?: $this->normalizePhone((string) ($message['phone'] ?? '')),
                'remote_jid' => $existing->remote_jid ?: $this->normalizeRemoteJid((string) ($message['remote_jid'] ?? '')),
                'body_text' => $existing->body_text ?: $this->nullableString($message['body_text'] ?? null),
                'last_event_name' => $this->nullableString($message['last_event_name'] ?? null),
                'received_at' => $existing->received_at ?: ($message['received_at'] ?? now()),
                'last_status_at' => $existing->last_status_at ?: ($message['received_at'] ?? now()),
                'payload' => $existing->payload ?: ($message['payload'] ?? null),
                'metadata' => $metadata,
            ]);

            return [
                'log' => $existing->fresh(),
                'was_created' => false,
                'already_handled' => filled(data_get($existing->metadata, 'automation.handled_at')),
            ];
        }

        $log = EvolutionMessageLog::query()->create([
            'provider' => $provider,
            'module' => $module,
            'direction' => 'inbound',
            'status' => $this->nullableString($message['status'] ?? null) ?: 'received',
            'message_type' => $this->nullableString($message['message_type'] ?? null),
            'external_message_id' => $externalMessageId !== '' ? $externalMessageId : null,
            'external_contact_id' => $this->nullableString($message['external_contact_id'] ?? null),
            'phone' => $this->normalizePhone((string) ($message['phone'] ?? '')),
            'remote_jid' => $this->normalizeRemoteJid((string) ($message['remote_jid'] ?? '')),
            'body_text' => $this->nullableString($message['body_text'] ?? null),
            'payload' => $message['payload'] ?? null,
            'metadata' => $metadata,
            'last_event_name' => $this->nullableString($message['last_event_name'] ?? null),
            'received_at' => $message['received_at'] ?? now(),
            'last_status_at' => $message['received_at'] ?? now(),
        ]);

        return [
            'log' => $log,
            'was_created' => true,
            'already_handled' => false,
        ];
    }

    public function markInboundHandled(EvolutionMessageLog $log, ?AutomationSession $session = null, array $metadata = []): EvolutionMessageLog
    {
        if (!$this->tablesReady() || !$log->exists) {
            $log->automation_session_id = $session?->id ?? $log->automation_session_id;
            $log->metadata = $this->mergeMetadata(
                $log->metadata,
                $metadata,
                [
                    'automation' => [
                        'handled_at' => now()->toIso8601String(),
                        'session_id' => $session?->id,
                        'session_protocol' => $session?->protocol,
                    ],
                ]
            );

            return $log;
        }

        $log->update([
            'automation_session_id' => $session?->id ?? $log->automation_session_id,
            'metadata' => $this->mergeMetadata(
                $log->metadata,
                $metadata,
                [
                    'automation' => [
                        'handled_at' => now()->toIso8601String(),
                        'session_id' => $session?->id,
                        'session_protocol' => $session?->protocol,
                    ],
                ]
            ),
        ]);

        return $log->fresh();
    }

    public function recordOutbound(string $module, string $phone, string $bodyText, array $response, array $context = []): EvolutionMessageLog
    {
        if (!$this->tablesReady()) {
            return new EvolutionMessageLog([
                'provider' => trim((string) ($context['provider'] ?? 'evolution')) ?: 'evolution',
                'module' => $module,
                'direction' => 'outbound',
                'status' => $this->normalizeStatus($response['status'] ?? null) ?: 'PENDING',
                'phone' => $this->normalizePhone($phone),
                'remote_jid' => $this->normalizeRemoteJid((string) ($response['remote_jid'] ?? ($context['remote_jid'] ?? ''))),
                'body_text' => trim($bodyText),
                'external_message_id' => $this->nullableString($response['message_id'] ?? null),
            ]);
        }

        $provider = trim((string) ($context['provider'] ?? 'evolution')) ?: 'evolution';
        $messageId = trim((string) ($response['message_id'] ?? ''));
        $status = $this->normalizeStatus($response['status'] ?? null) ?: 'PENDING';
        $remoteJid = $this->normalizeRemoteJid((string) ($response['remote_jid'] ?? ($context['remote_jid'] ?? '')));
        $sentAt = $context['sent_at'] ?? now();

        $log = $messageId !== ''
            ? EvolutionMessageLog::query()->firstOrNew([
                'provider' => $provider,
                'external_message_id' => $messageId,
            ])
            : new EvolutionMessageLog();

        $log->fill([
            'provider' => $provider,
            'module' => $module,
            'direction' => 'outbound',
            'status' => $status,
            'message_type' => $this->nullableString($context['message_type'] ?? 'text'),
            'external_contact_id' => $this->nullableString($context['external_contact_id'] ?? null),
            'phone' => $this->normalizePhone($phone),
            'remote_jid' => $remoteJid,
            'body_text' => trim($bodyText),
            'payload' => $response,
            'metadata' => $this->mergeMetadata($log->metadata, $context['metadata'] ?? [], [
                'context' => $this->onlyContextKeys($context),
            ]),
            'last_event_name' => $this->nullableString($context['last_event_name'] ?? 'SEND_MESSAGE'),
            'automation_session_id' => $context['automation_session_id'] ?? $log->automation_session_id,
            'process_case_id' => $context['process_case_id'] ?? $log->process_case_id,
            'process_case_phase_id' => $context['process_case_phase_id'] ?? $log->process_case_phase_id,
            'cobranca_case_id' => $context['cobranca_case_id'] ?? $log->cobranca_case_id,
            'sent_at' => $log->sent_at ?: $sentAt,
            'last_status_at' => $sentAt,
        ]);

        $this->applyStatusTimestamps($log, $status, $sentAt);
        $log->save();

        return $log->fresh();
    }

    public function recordOutboundFailure(string $module, string $phone, string $bodyText, string $errorMessage, array $context = []): EvolutionMessageLog
    {
        if (!$this->tablesReady()) {
            return new EvolutionMessageLog([
                'provider' => trim((string) ($context['provider'] ?? 'evolution')) ?: 'evolution',
                'module' => $module,
                'direction' => 'outbound',
                'status' => 'FAILED',
                'phone' => $this->normalizePhone($phone),
                'body_text' => trim($bodyText),
                'metadata' => [
                    'error_message' => $errorMessage,
                ],
            ]);
        }

        return EvolutionMessageLog::query()->create([
            'provider' => trim((string) ($context['provider'] ?? 'evolution')) ?: 'evolution',
            'module' => $module,
            'direction' => 'outbound',
            'status' => 'FAILED',
            'message_type' => $this->nullableString($context['message_type'] ?? 'text'),
            'external_contact_id' => $this->nullableString($context['external_contact_id'] ?? null),
            'phone' => $this->normalizePhone($phone),
            'remote_jid' => $this->normalizeRemoteJid((string) ($context['remote_jid'] ?? '')),
            'body_text' => trim($bodyText),
            'payload' => $context['payload'] ?? null,
            'metadata' => $this->mergeMetadata(null, $context['metadata'] ?? [], [
                'context' => $this->onlyContextKeys($context),
                'error_message' => $errorMessage,
            ]),
            'last_event_name' => $this->nullableString($context['last_event_name'] ?? 'SEND_MESSAGE'),
            'automation_session_id' => $context['automation_session_id'] ?? null,
            'process_case_id' => $context['process_case_id'] ?? null,
            'process_case_phase_id' => $context['process_case_phase_id'] ?? null,
            'cobranca_case_id' => $context['cobranca_case_id'] ?? null,
            'last_status_at' => now(),
            'failed_at' => now(),
        ]);
    }

    public function applyStatusUpdate(string $eventName, array $payload, array $context = []): ?EvolutionMessageLog
    {
        if (!$this->tablesReady()) {
            return null;
        }

        $messageId = $this->messageIdFromPayload($payload);
        if ($messageId === '') {
            return null;
        }

        $log = EvolutionMessageLog::query()
            ->where('provider', trim((string) ($context['provider'] ?? 'evolution')) ?: 'evolution')
            ->where('external_message_id', $messageId)
            ->first();

        if (!$log) {
            return null;
        }

        $status = $this->statusFromPayload($payload, $eventName) ?: $log->status;
        $occurredAt = $this->timestampFromPayload($payload) ?? now();

        $log->fill([
            'status' => $status,
            'phone' => $log->phone ?: $this->phoneFromPayload($payload),
            'remote_jid' => $log->remote_jid ?: $this->remoteJidFromPayload($payload),
            'message_type' => $log->message_type ?: $this->messageTypeFromPayload($payload),
            'body_text' => $log->body_text ?: $this->bodyTextFromPayload($payload),
            'last_event_name' => $eventName,
            'last_status_at' => $occurredAt,
            'metadata' => $this->mergeMetadata($log->metadata, $context['metadata'] ?? [], [
                'last_webhook_payload' => $payload,
                'last_webhook_event_id' => $context['webhook_event_id'] ?? null,
                'last_webhook_received_at' => $occurredAt->toIso8601String(),
            ]),
        ]);

        $this->applyStatusTimestamps($log, $status, $occurredAt);
        $log->save();

        return $log->fresh();
    }

    public function messageIdFromPayload(array $payload): string
    {
        $candidates = [
            data_get($payload, 'key.id'),
            data_get($payload, 'message.key.id'),
            data_get($payload, 'data.key.id'),
            data_get($payload, 'id'),
            data_get($payload, 'messageId'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function remoteJidFromPayload(array $payload): string
    {
        $candidates = [
            data_get($payload, 'key.remoteJid'),
            data_get($payload, 'message.key.remoteJid'),
            data_get($payload, 'data.key.remoteJid'),
            data_get($payload, 'remoteJid'),
            data_get($payload, 'jid'),
            data_get($payload, 'participant'),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->normalizeRemoteJid((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function phoneFromPayload(array $payload): string
    {
        $candidates = [
            data_get($payload, 'phone'),
            data_get($payload, 'number'),
            data_get($payload, 'sender'),
            data_get($payload, 'from'),
            $this->remoteJidFromPayload($payload),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->normalizePhone((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function externalContactIdFromPayload(array $payload): string
    {
        $candidates = [
            data_get($payload, 'contactId'),
            data_get($payload, 'contact.id'),
            data_get($payload, 'numberId'),
            $this->remoteJidFromPayload($payload),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function bodyTextFromPayload(array $payload): string
    {
        $candidates = [
            data_get($payload, 'message.conversation'),
            data_get($payload, 'message.extendedTextMessage.text'),
            data_get($payload, 'message.imageMessage.caption'),
            data_get($payload, 'message.videoMessage.caption'),
            data_get($payload, 'message.documentMessage.caption'),
            data_get($payload, 'message.buttonsResponseMessage.selectedDisplayText'),
            data_get($payload, 'message.buttonsResponseMessage.selectedButtonId'),
            data_get($payload, 'message.listResponseMessage.title'),
            data_get($payload, 'message.listResponseMessage.description'),
            data_get($payload, 'message.listResponseMessage.singleSelectReply.selectedRowId'),
            data_get($payload, 'message.listResponseMessage.singleSelectReply.title'),
            data_get($payload, 'message.templateButtonReplyMessage.selectedDisplayText'),
            data_get($payload, 'message.templateButtonReplyMessage.selectedId'),
            data_get($payload, 'message.interactiveResponseMessage.body.text'),
            data_get($payload, 'text'),
            data_get($payload, 'content'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function messageTypeFromPayload(array $payload): ?string
    {
        $type = trim((string) (data_get($payload, 'messageType') ?: data_get($payload, 'type') ?: ''));
        if ($type !== '') {
            return $type;
        }

        $message = data_get($payload, 'message');
        if (is_array($message) && $message !== []) {
            $key = array_key_first($message);
            if (is_string($key) && trim($key) !== '') {
                return trim($key);
            }
        }

        return null;
    }

    public function statusFromPayload(array $payload, ?string $eventName = null): ?string
    {
        $candidates = [
            data_get($payload, 'status'),
            data_get($payload, 'message.status'),
            data_get($payload, 'data.status'),
            data_get($payload, 'update.status'),
            data_get($payload, 'messageStatus'),
            data_get($payload, 'ack'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeStatus($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        if ($eventName === 'MESSAGES_DELETE') {
            return 'DELETED';
        }

        return null;
    }

    public function timestampFromPayload(array $payload): ?Carbon
    {
        $candidates = [
            data_get($payload, 'messageTimestamp'),
            data_get($payload, 'message.timestamp'),
            data_get($payload, 'timestamp'),
            data_get($payload, 'messageTime'),
        ];

        foreach ($candidates as $candidate) {
            $resolved = $this->resolveTimestamp($candidate);
            if ($resolved) {
                return $resolved;
            }
        }

        return null;
    }

    public function normalizePhone(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $value = explode('@', $value, 2)[0];
        }

        if (str_contains($value, ':')) {
            $value = explode(':', $value, 2)[0];
        }

        return preg_replace('/\D+/', '', $value) ?: '';
    }

    public function normalizeRemoteJid(string $value): string
    {
        return trim(strtolower($value));
    }

    public function normalizeStatus(mixed $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        if (is_numeric($status)) {
            return match ((int) $status) {
                0 => 'ERROR',
                1 => 'PENDING',
                2 => 'SERVER_ACK',
                3 => 'DELIVERY_ACK',
                4 => 'READ',
                5 => 'PLAYED',
                default => 'ACK_' . (int) $status,
            };
        }

        $normalized = strtoupper(trim((string) $status));
        if ($normalized === '') {
            return null;
        }

        return str_replace([' ', '-'], '_', $normalized);
    }

    private function applyStatusTimestamps(EvolutionMessageLog $log, string $status, CarbonInterface $occurredAt): void
    {
        if ($log->direction === 'outbound' && !$log->sent_at) {
            $log->sent_at = $occurredAt;
        }

        if (in_array($status, ['DELIVERY_ACK', 'DELIVERED'], true) && !$log->delivered_at) {
            $log->delivered_at = $occurredAt;
        }

        if (in_array($status, ['READ', 'READ_ACK', 'PLAYED'], true) && !$log->read_at) {
            $log->read_at = $occurredAt;
            $log->delivered_at ??= $occurredAt;
        }

        if (in_array($status, ['FAILED', 'ERROR'], true) && !$log->failed_at) {
            $log->failed_at = $occurredAt;
        }
    }

    private function resolveTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (string) $value;

            return strlen($timestamp) > 10
                ? Carbon::createFromTimestampMs((int) $timestamp)
                : Carbon::createFromTimestamp((int) $timestamp);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function onlyContextKeys(array $context): array
    {
        return array_filter([
            'event_name' => $this->nullableString($context['event_name'] ?? null),
            'webhook_event_id' => $context['webhook_event_id'] ?? null,
            'instance_name' => $this->nullableString($context['instance_name'] ?? null),
            'process_case_id' => $context['process_case_id'] ?? null,
            'process_case_phase_id' => $context['process_case_phase_id'] ?? null,
            'cobranca_case_id' => $context['cobranca_case_id'] ?? null,
            'automation_session_id' => $context['automation_session_id'] ?? null,
        ], fn ($value) => !($value === null || $value === ''));
    }

    private function mergeMetadata(?array $current, array ...$layers): array
    {
        $metadata = is_array($current) ? $current : [];

        foreach ($layers as $layer) {
            $metadata = array_replace_recursive($metadata, array_filter($layer, function ($value) {
                return $value !== null;
            }));
        }

        return $metadata;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function tablesReady(): bool
    {
        if ($this->tablesReady !== null) {
            return $this->tablesReady;
        }

        return $this->tablesReady = Schema::hasTable('evolution_message_logs');
    }
}
