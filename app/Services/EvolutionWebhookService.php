<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AutomationSession;
use App\Models\EvolutionWebhookEvent;
use App\Services\Automation\AutomationConversationService;
use App\Services\Automation\IncomingAutomationMessageData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class EvolutionWebhookService
{
    private ?bool $eventTableReady = null;

    public function __construct(
        private readonly EvolutionApiService $evolutionApiService,
        private readonly EvolutionMessageLogService $messageLogs,
        private readonly AutomationConversationService $automationConversation,
    ) {
    }

    public function handle(?string $routeEvent, array $payload): array
    {
        $eventName = $this->normalizeEventName(
            $routeEvent
            ?: data_get($payload, 'event')
            ?: data_get($payload, 'data.event')
        );

        $referencePayload = $this->referencePayload($payload);
        $event = $this->newWebhookEvent($eventName, $payload, $referencePayload);

        try {
            $result = match ($eventName) {
                'MESSAGES_UPSERT' => $this->handleMessagesUpsert($event, $payload),
                'MESSAGES_UPDATE', 'SEND_MESSAGE', 'MESSAGES_DELETE' => $this->handleMessageStatusEvent($event, $payload),
                'CONNECTION_UPDATE' => $this->handleConnectionUpdate($event, $payload),
                'QRCODE_UPDATED' => $this->handleQrCodeUpdate($event, $payload),
                default => [
                    'processing_status' => 'ignored',
                    'message' => 'Evento recebido sem rotina especifica no Ancora.',
                ],
            };

            $processingStatus = (string) ($result['processing_status'] ?? 'processed');
            $processingMessage = $this->nullableString($result['message'] ?? null);

            $event->forceFill([
                'processing_status' => $processingStatus,
                'processing_message' => $processingMessage,
                'context' => $result,
                'processed_at' => now(),
            ]);
            if ($event->exists) {
                $event->save();
            }

            $this->rememberWebhookReceipt($eventName, $processingStatus, $event->processed_at ?? now());

            return [
                'ok' => true,
                'event' => $eventName,
                'processing_status' => $processingStatus,
                'message' => $processingMessage,
                'summary' => $result,
            ];
        } catch (\Throwable $exception) {
            $event->forceFill([
                'processing_status' => 'failed',
                'processing_message' => mb_substr($exception->getMessage(), 0, 65535),
                'context' => [
                    'exception' => get_class($exception),
                ],
                'processed_at' => now(),
            ]);
            if ($event->exists) {
                $event->save();
            }

            $this->rememberWebhookReceipt($eventName, 'failed', $event->processed_at ?? now());

            throw $exception;
        }
    }

    private function handleMessagesUpsert(EvolutionWebhookEvent $event, array $payload): array
    {
        $settings = $this->evolutionApiService->currentSettings();
        $items = $this->eventItems($payload);

        $processed = 0;
        $duplicates = 0;
        $ignored = 0;
        $replySent = 0;

        foreach ($items as $item) {
            $remoteJid = $this->messageLogs->remoteJidFromPayload($item);
            $phone = $this->messageLogs->phoneFromPayload($item);
            $messageText = $this->messageLogs->bodyTextFromPayload($item);

            if ($this->isFromMe($item)) {
                $ignored++;
                continue;
            }

            if ($remoteJid !== '' && str_ends_with($remoteJid, '@g.us')) {
                $ignored++;
                continue;
            }

            if ($phone === '' || $messageText === '') {
                $ignored++;
                continue;
            }

            $record = $this->messageLogs->recordInbound('automation', [
                'provider' => 'evolution',
                'external_message_id' => $this->messageLogs->messageIdFromPayload($item),
                'external_contact_id' => $this->messageLogs->externalContactIdFromPayload($item),
                'phone' => $phone,
                'remote_jid' => $remoteJid,
                'message_type' => $this->messageLogs->messageTypeFromPayload($item),
                'body_text' => $messageText,
                'status' => 'RECEIVED',
                'received_at' => $this->messageLogs->timestampFromPayload($item) ?? now(),
                'last_event_name' => $event->event_name,
                'payload' => $item,
            ], [
                'event_name' => (string) $event->event_name,
                'instance_name' => $event->instance_name,
                'webhook_event_id' => $event->id,
                'metadata' => [
                    'push_name' => $this->nullableString(data_get($item, 'pushName')),
                    'source_event' => (string) $event->event_name,
                ],
            ]);

            if ($record['already_handled']) {
                $duplicates++;
                continue;
            }

            $message = IncomingAutomationMessageData::fromArray([
                'channel' => 'whatsapp',
                'provider' => 'evolution',
                'phone' => $phone,
                'external_contact_id' => $this->messageLogs->externalContactIdFromPayload($item),
                'external_message_id' => $this->messageLogs->messageIdFromPayload($item),
                'message_text' => $messageText,
                'timestamp' => optional($this->messageLogs->timestampFromPayload($item))->toIso8601String(),
                'metadata' => [
                    'push_name' => $this->nullableString(data_get($item, 'pushName')),
                    'remote_jid' => $remoteJid,
                    'message_type' => $this->messageLogs->messageTypeFromPayload($item),
                    'webhook_event_id' => $event->id,
                ],
            ]);

            $response = $this->automationConversation->process($message);
            if (!data_get($response, 'ok', false)) {
                throw new \RuntimeException((string) (data_get($response, 'error.message') ?: 'Falha ao processar a conversa automatica do WhatsApp.'));
            }

            $session = $this->resolveAutomationSession($response, $phone);
            $replyText = trim((string) data_get($response, 'action.message'));
            $shouldReply = data_get($response, 'action.type') === 'reply' && $replyText !== '';

            if ($shouldReply) {
                if (!$this->evolutionApiService->hasReadyConfiguration($settings)) {
                    throw new \RuntimeException('A EvolutionAPI nao esta configurada para responder mensagens inbound.');
                }

                $sendResponse = $this->evolutionApiService->sendTextMessage($settings, $phone, $replyText);
                $replyLog = $this->messageLogs->recordOutbound('automation', $phone, $replyText, $sendResponse, [
                    'automation_session_id' => $session?->id,
                    'last_event_name' => 'SEND_MESSAGE',
                    'metadata' => [
                        'automation' => [
                            'reply_to_inbound_log_id' => $record['log']->id,
                            'reply_to_inbound_message_id' => $record['log']->external_message_id,
                            'human_handover' => (bool) data_get($response, 'action.human_handover'),
                            'close_session' => (bool) data_get($response, 'action.close_session'),
                        ],
                    ],
                ]);

                $this->messageLogs->markInboundHandled($record['log'], $session, [
                    'automation' => [
                        'reply_message_log_id' => $replyLog->id,
                        'reply_message_id' => $replyLog->external_message_id,
                        'reply_status' => $replyLog->status,
                    ],
                ]);

                $replySent++;
            } else {
                $this->messageLogs->markInboundHandled($record['log'], $session, [
                    'automation' => [
                        'reply_skipped' => true,
                        'human_handover' => (bool) data_get($response, 'action.human_handover'),
                        'close_session' => (bool) data_get($response, 'action.close_session'),
                    ],
                ]);
            }

            $processed++;
            $this->rememberInboundReceipt($phone, $messageText, $event->received_at ?? now());
        }

        return [
            'processing_status' => ($processed > 0 || $duplicates > 0) ? 'processed' : 'ignored',
            'message' => $processed > 0
                ? 'Mensagens inbound processadas com sucesso.'
                : 'Nenhuma mensagem inbound elegivel foi processada.',
            'processed' => $processed,
            'duplicates' => $duplicates,
            'ignored' => $ignored,
            'reply_sent' => $replySent,
        ];
    }

    private function handleMessageStatusEvent(EvolutionWebhookEvent $event, array $payload): array
    {
        $items = $this->eventItems($payload);
        $updated = 0;
        $ignored = 0;

        foreach ($items as $item) {
            $log = $this->messageLogs->applyStatusUpdate((string) $event->event_name, $item, [
                'webhook_event_id' => $event->id,
            ]);

            if (!$log) {
                $ignored++;
                continue;
            }

            $updated++;
            $this->rememberLastStatus($log, $event->received_at ?? now());
        }

        return [
            'processing_status' => $updated > 0 ? 'processed' : 'ignored',
            'message' => $updated > 0
                ? 'Status de mensagens atualizado com sucesso.'
                : 'Nenhuma mensagem conhecida foi encontrada para atualizar o status.',
            'updated' => $updated,
            'ignored' => $ignored,
        ];
    }

    private function handleConnectionUpdate(EvolutionWebhookEvent $event, array $payload): array
    {
        $reference = $this->referencePayload($payload);
        $state = trim((string) (
            data_get($reference, 'instance.state')
            ?: data_get($reference, 'state')
            ?: data_get($payload, 'instance.state')
            ?: data_get($payload, 'state')
        ));
        $instanceName = $this->instanceNameFromPayload($payload) ?: $event->instance_name;
        $receivedAt = $event->received_at ?? now();

        AppSetting::setValue('evolution_last_connection_state', $state, 'Ultimo estado de conexao recebido via webhook da EvolutionAPI');
        AppSetting::setValue('evolution_last_connection_instance', (string) $instanceName, 'Ultima instancia de conexao recebida via webhook da EvolutionAPI');
        AppSetting::setValue('evolution_last_connection_at', $receivedAt->toIso8601String(), 'Ultimo horario de conexao recebido via webhook da EvolutionAPI');

        return [
            'processing_status' => $state !== '' ? 'processed' : 'ignored',
            'message' => $state !== ''
                ? 'Estado de conexao atualizado para ' . strtoupper($state) . '.'
                : 'Evento de conexao recebido sem estado identificavel.',
            'state' => $state,
            'instance_name' => $instanceName,
        ];
    }

    private function handleQrCodeUpdate(EvolutionWebhookEvent $event, array $payload): array
    {
        $receivedAt = $event->received_at ?? now();
        AppSetting::setValue('evolution_last_qrcode_at', $receivedAt->toIso8601String(), 'Ultimo horario de atualizacao de QRCode recebido via webhook da EvolutionAPI');

        return [
            'processing_status' => 'processed',
            'message' => 'Evento de QRCode registrado para auditoria.',
        ];
    }

    private function eventItems(array $payload): array
    {
        $data = $payload['data'] ?? null;

        if (is_array($data) && array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        if (is_array($data) && is_array($data['messages'] ?? null)) {
            return array_values(array_filter((array) $data['messages'], 'is_array'));
        }

        if (is_array($payload['messages'] ?? null)) {
            return array_values(array_filter((array) $payload['messages'], 'is_array'));
        }

        if (is_array($data) && $data !== []) {
            return [$data];
        }

        return [$payload];
    }

    private function referencePayload(array $payload): array
    {
        $items = $this->eventItems($payload);

        return $items[0] ?? $payload;
    }

    private function normalizeEventName(mixed $value): string
    {
        $value = trim((string) $value, "/ \t\n\r\0\x0B");

        return $value !== '' ? strtoupper($value) : 'UNKNOWN';
    }

    private function messageDirectionFromPayload(array $payload): ?string
    {
        if ($payload === []) {
            return null;
        }

        return $this->isFromMe($payload) ? 'outbound' : 'inbound';
    }

    private function isFromMe(array $payload): bool
    {
        return (bool) (data_get($payload, 'key.fromMe') ?? data_get($payload, 'fromMe') ?? false);
    }

    private function instanceNameFromPayload(array $payload): string
    {
        $candidates = [
            data_get($payload, 'instance.instanceName'),
            data_get($payload, 'instanceName'),
            data_get($payload, 'instance'),
            data_get($payload, 'data.instance.instanceName'),
            data_get($payload, 'data.instanceName'),
            data_get($payload, 'data.instance'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveAutomationSession(array $response, string $phone): ?AutomationSession
    {
        $protocol = trim((string) data_get($response, 'session.protocol'));
        if ($protocol !== '') {
            return AutomationSession::query()->where('protocol', $protocol)->first();
        }

        return AutomationSession::query()
            ->where('channel', 'whatsapp')
            ->where('phone', $phone)
            ->latest('last_interaction_at')
            ->first();
    }

    private function rememberWebhookReceipt(string $eventName, string $processingStatus, Carbon $receivedAt): void
    {
        AppSetting::setValue('evolution_last_webhook_event_name', $eventName, 'Ultimo evento recebido via webhook da EvolutionAPI');
        AppSetting::setValue('evolution_last_webhook_status', $processingStatus, 'Ultimo status de processamento do webhook da EvolutionAPI');
        AppSetting::setValue('evolution_last_webhook_at', $receivedAt->toIso8601String(), 'Ultimo horario de webhook recebido da EvolutionAPI');
    }

    private function rememberInboundReceipt(string $phone, string $messageText, Carbon $receivedAt): void
    {
        AppSetting::setValue('evolution_last_inbound_phone', $phone, 'Ultimo telefone que enviou mensagem inbound via EvolutionAPI');
        AppSetting::setValue('evolution_last_inbound_message', mb_substr($messageText, 0, 255), 'Ultima mensagem inbound recebida via EvolutionAPI');
        AppSetting::setValue('evolution_last_inbound_at', $receivedAt->toIso8601String(), 'Ultimo horario de mensagem inbound recebida via EvolutionAPI');
    }

    private function rememberLastStatus($log, Carbon $receivedAt): void
    {
        AppSetting::setValue('evolution_last_message_status', (string) $log->status, 'Ultimo status de mensagem atualizado via webhook da EvolutionAPI');
        AppSetting::setValue('evolution_last_message_status_module', (string) $log->module, 'Ultimo modulo com status de mensagem atualizado via webhook da EvolutionAPI');
        AppSetting::setValue('evolution_last_message_status_phone', (string) ($log->phone ?: ''), 'Ultimo telefone com status de mensagem atualizado via webhook da EvolutionAPI');
        AppSetting::setValue('evolution_last_message_status_at', $receivedAt->toIso8601String(), 'Ultimo horario de status de mensagem atualizado via webhook da EvolutionAPI');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function newWebhookEvent(string $eventName, array $payload, array $referencePayload): EvolutionWebhookEvent
    {
        $attributes = [
            'provider' => 'evolution',
            'event_name' => $eventName,
            'instance_name' => $this->instanceNameFromPayload($payload),
            'processing_status' => 'pending',
            'message_direction' => $this->messageDirectionFromPayload($referencePayload),
            'message_id' => $this->nullableString($this->messageLogs->messageIdFromPayload($referencePayload)),
            'remote_jid' => $this->nullableString($this->messageLogs->remoteJidFromPayload($referencePayload)),
            'phone' => $this->nullableString($this->messageLogs->phoneFromPayload($referencePayload)),
            'message_status' => $this->nullableString($this->messageLogs->statusFromPayload($referencePayload, $eventName)),
            'payload' => $payload,
            'received_at' => now(),
        ];

        if (!$this->eventTableReady()) {
            return new EvolutionWebhookEvent($attributes);
        }

        return EvolutionWebhookEvent::query()->create($attributes);
    }

    private function eventTableReady(): bool
    {
        if ($this->eventTableReady !== null) {
            return $this->eventTableReady;
        }

        return $this->eventTableReady = Schema::hasTable('evolution_webhook_events');
    }
}
