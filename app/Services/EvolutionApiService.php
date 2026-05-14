<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class EvolutionApiService
{
    public static function defaultDispatchDelayMs(): int
    {
        return 3000;
    }

    public function currentSettings(): array
    {
        $processTemplate = trim((string) AppSetting::getValue('evolution_template_process_update', ''));
        $collectionTemplate = trim((string) AppSetting::getValue('evolution_template_collection_notice', ''));
        $collectionEmailSubject = trim((string) AppSetting::getValue('evolution_template_collection_email_subject', ''));
        $collectionEmailBody = trim((string) AppSetting::getValue('evolution_template_collection_email_body', ''));

        return [
            'evolution_enabled' => AppSetting::getValue('evolution_enabled', '0') === '1',
            'evolution_base_url' => rtrim((string) AppSetting::getValue('evolution_base_url', ''), '/'),
            'evolution_instance_name' => (string) AppSetting::getValue('evolution_instance_name', ''),
            'evolution_api_key' => (string) AppSetting::getDecryptedValue('evolution_api_key', ''),
            'evolution_webhook_enabled' => AppSetting::getValue('evolution_webhook_enabled', '1') === '1',
            'evolution_webhook_by_events' => AppSetting::getValue('evolution_webhook_by_events', '0') === '1',
            'evolution_webhook_token' => (string) AppSetting::getValue('evolution_webhook_token', ''),
            'evolution_webhook_events' => $this->storedWebhookEvents(),
            'evolution_message_dispatch_delay_ms' => (int) AppSetting::getValue('evolution_message_dispatch_delay_ms', (string) static::defaultDispatchDelayMs()),
            'evolution_template_process_update' => $processTemplate !== '' ? $processTemplate : NotificationTemplateService::defaultProcessWhatsappTemplate(),
            'evolution_template_collection_notice' => $collectionTemplate !== '' ? $collectionTemplate : NotificationTemplateService::defaultCollectionWhatsappTemplate(),
            'evolution_template_collection_email_subject' => $collectionEmailSubject !== '' ? $collectionEmailSubject : NotificationTemplateService::defaultCollectionEmailSubject(),
            'evolution_template_collection_email_body' => $collectionEmailBody !== '' ? $collectionEmailBody : NotificationTemplateService::defaultCollectionEmailBody(),
        ];
    }

    public function hasReadyConfiguration(?array $settings = null): bool
    {
        $settings ??= $this->currentSettings();

        if (!($settings['evolution_enabled'] ?? false)) {
            return false;
        }

        return trim((string) ($settings['evolution_base_url'] ?? '')) !== ''
            && trim((string) ($settings['evolution_instance_name'] ?? '')) !== ''
            && trim((string) ($settings['evolution_api_key'] ?? '')) !== '';
    }

    public static function availableWebhookEvents(): array
    {
        return [
            [
                'value' => 'CONNECTION_UPDATE',
                'label' => 'Atualizacao de conexao',
                'description' => 'Mudancas no estado da sessao do numero conectado.',
            ],
            [
                'value' => 'QRCODE_UPDATED',
                'label' => 'QRCode da instancia',
                'description' => 'Atualizacoes do QRCode quando a instancia precisar reconectar.',
            ],
            [
                'value' => 'MESSAGES_UPSERT',
                'label' => 'Novas mensagens',
                'description' => 'Mensagens recebidas pela instancia.',
            ],
            [
                'value' => 'MESSAGES_UPDATE',
                'label' => 'Status de mensagens',
                'description' => 'Mudancas de status e atualizacoes das mensagens.',
            ],
            [
                'value' => 'MESSAGES_DELETE',
                'label' => 'Mensagens excluidas',
                'description' => 'Eventos de exclusao de mensagens.',
            ],
            [
                'value' => 'SEND_MESSAGE',
                'label' => 'Mensagens enviadas',
                'description' => 'Retorno dos disparos feitos pelo Ancora.',
            ],
        ];
    }

    public static function defaultWebhookEvents(): array
    {
        return [
            'CONNECTION_UPDATE',
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'SEND_MESSAGE',
        ];
    }

    public static function messageVariableDefinitions(): array
    {
        return NotificationTemplateService::variableDefinitions();
    }

    public static function defaultProcessTemplate(): string
    {
        return NotificationTemplateService::defaultProcessWhatsappTemplate();
    }

    public static function defaultCollectionTemplate(): string
    {
        return NotificationTemplateService::defaultCollectionWhatsappTemplate();
    }

    public static function defaultCollectionEmailSubjectTemplate(): string
    {
        return NotificationTemplateService::defaultCollectionEmailSubject();
    }

    public static function defaultCollectionEmailBodyTemplate(): string
    {
        return NotificationTemplateService::defaultCollectionEmailBody();
    }

    public function webhookUrl(array $settings): string
    {
        $token = trim((string) ($settings['evolution_webhook_token'] ?? ''));

        if ($token === '') {
            return url('/api/integrations/evolution/webhook');
        }

        return route('integrations.evolution.webhook', ['token' => $token], true);
    }

    public function testConnection(array $settings): array
    {
        $this->assertConfigured($settings);

        $response = $this->request($settings)->get('/instance/connectionState/' . rawurlencode((string) $settings['evolution_instance_name']));
        if (!$response->successful()) {
            throw new \RuntimeException($this->extractErrorMessage($response, 'Nao foi possivel consultar o estado da instancia na EvolutionAPI.'));
        }

        $payload = $this->decodeArray($response, 'Resposta invalida ao consultar o estado da instancia na EvolutionAPI.');
        $state = (string) (data_get($payload, 'instance.state') ?: data_get($payload, 'state') ?: 'desconhecido');

        return [
            'instance_name' => (string) (data_get($payload, 'instance.instanceName') ?: $settings['evolution_instance_name']),
            'state' => $state,
            'webhook' => $this->findWebhook($settings, false),
        ];
    }

    public function syncWebhook(array $settings): array
    {
        $this->assertConfigured($settings);

        $payload = [
            'enabled' => (bool) ($settings['evolution_webhook_enabled'] ?? true),
            'url' => $this->webhookUrl($settings),
            'webhook_by_events' => (bool) ($settings['evolution_webhook_by_events'] ?? false),
            'events' => array_values((array) ($settings['evolution_webhook_events'] ?? static::defaultWebhookEvents())),
        ];

        $response = $this->request($settings)->post('/webhook/set/' . rawurlencode((string) $settings['evolution_instance_name']), $payload);
        if (!$response->successful()) {
            throw new \RuntimeException($this->extractErrorMessage($response, 'Nao foi possivel sincronizar o webhook na EvolutionAPI.'));
        }

        return [
            'payload' => $payload,
            'remote_webhook' => $this->findWebhook($settings, true),
        ];
    }

    public function sendTestMessage(array $settings, string $number, string $message): array
    {
        return $this->sendTextMessage($settings, $number, $message);
    }

    public function sendTextMessage(array $settings, string $number, string $message): array
    {
        $this->assertConfigured($settings);

        $normalizedNumber = $this->normalizePhoneNumber($number);
        if ($normalizedNumber === '' || strlen($normalizedNumber) < 10) {
            throw new \RuntimeException('Informe um numero com DDI, usando apenas numeros.');
        }

        $text = trim($message);
        if ($text === '') {
            throw new \RuntimeException('Informe a mensagem que sera enviada.');
        }

        $response = $this->request($settings)->post('/message/sendText/' . rawurlencode((string) $settings['evolution_instance_name']), [
            'number' => $normalizedNumber,
            'text' => $text,
            'delay' => max(0, (int) ($settings['evolution_message_dispatch_delay_ms'] ?? static::defaultDispatchDelayMs())),
            'linkPreview' => false,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($this->extractErrorMessage($response, 'Nao foi possivel enviar a mensagem pela EvolutionAPI.'));
        }

        $payload = $this->decodeArray($response, 'Resposta invalida ao enviar a mensagem pela EvolutionAPI.');

        return [
            'status' => (string) (data_get($payload, 'status') ?: 'PENDING'),
            'message_id' => (string) (data_get($payload, 'key.id') ?: ''),
            'remote_jid' => (string) (data_get($payload, 'key.remoteJid') ?: ''),
        ];
    }

    private function findWebhook(array $settings, bool $throwOnFailure = true): ?array
    {
        $response = $this->request($settings)->get('/webhook/find/' . rawurlencode((string) $settings['evolution_instance_name']));

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            if ($throwOnFailure) {
                throw new \RuntimeException($this->extractErrorMessage($response, 'Nao foi possivel consultar o webhook configurado na EvolutionAPI.'));
            }

            return null;
        }

        $payload = $this->decodeArray($response, 'Resposta invalida ao consultar o webhook da EvolutionAPI.');

        return [
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'url' => (string) ($payload['url'] ?? ''),
            'webhook_by_events' => (bool) ($payload['webhookByEvents'] ?? $payload['webhook_by_events'] ?? false),
            'events' => array_values((array) ($payload['events'] ?? [])),
        ];
    }

    private function request(array $settings): PendingRequest
    {
        return Http::baseUrl($this->normalizeBaseUrl((string) ($settings['evolution_base_url'] ?? '')))
            ->timeout(45)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'apikey' => trim((string) ($settings['evolution_api_key'] ?? '')),
            ]);
    }

    private function assertConfigured(array $settings): void
    {
        $missing = [];

        if (trim((string) ($settings['evolution_base_url'] ?? '')) === '') {
            $missing[] = 'URL base';
        }

        if (trim((string) ($settings['evolution_instance_name'] ?? '')) === '') {
            $missing[] = 'nome da instancia';
        }

        if (trim((string) ($settings['evolution_api_key'] ?? '')) === '') {
            $missing[] = 'API key';
        }

        if ($missing !== []) {
            throw new \RuntimeException('Configure ' . implode(', ', $missing) . ' antes de usar a EvolutionAPI.');
        }
    }

    private function normalizeBaseUrl(string $value): string
    {
        return rtrim(trim($value), '/');
    }

    private function storedWebhookEvents(): array
    {
        $storedEvents = json_decode((string) AppSetting::getValue('evolution_webhook_events_json', '[]'), true);
        $allowedEvents = collect(static::availableWebhookEvents())->pluck('value')->values()->all();
        $events = collect(is_array($storedEvents) ? $storedEvents : [])
            ->map(fn ($event) => trim((string) $event))
            ->filter(fn ($event) => in_array($event, $allowedEvents, true))
            ->values()
            ->all();

        return $events !== [] ? $events : static::defaultWebhookEvents();
    }

    private function normalizePhoneNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        $length = strlen($digits);

        if ($length === 10 || $length === 11) {
            return '55' . $digits;
        }

        return $digits;
    }

    private function decodeArray(Response $response, string $fallbackMessage): array
    {
        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException($fallbackMessage);
        }

        return $decoded;
    }

    private function extractErrorMessage(Response $response, string $fallbackMessage): string
    {
        $payload = $response->json();

        if (is_array($payload)) {
            foreach (['message', 'error', 'response'] as $key) {
                $value = trim((string) ($payload[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            $detail = trim((string) data_get($payload, 'details.message'));
            if ($detail !== '') {
                return $detail;
            }
        }

        return $fallbackMessage;
    }
}
