<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class EvolutionApiService
{
    public static function defaultDispatchDelayMs(): int
    {
        return 3000;
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
        return [
            ['key' => 'condominio_nome', 'label' => 'Nome do condominio', 'modules' => 'Processos e cobranca'],
            ['key' => 'unidade_numero', 'label' => 'Numero da unidade', 'modules' => 'Processos e cobranca'],
            ['key' => 'bloco_nome', 'label' => 'Nome do bloco', 'modules' => 'Processos e cobranca'],
            ['key' => 'vencimento', 'label' => 'Data(s) de vencimento', 'modules' => 'Cobranca'],
            ['key' => 'cliente_nome', 'label' => 'Nome do cliente/contato', 'modules' => 'Processos'],
            ['key' => 'devedor_nome', 'label' => 'Nome do devedor', 'modules' => 'Cobranca'],
            ['key' => 'processo_numero', 'label' => 'Numero do processo', 'modules' => 'Processos'],
            ['key' => 'ultimo_andamento', 'label' => 'Ultimo andamento', 'modules' => 'Processos'],
            ['key' => 'andamento_data', 'label' => 'Data do andamento', 'modules' => 'Processos'],
            ['key' => 'os_numero', 'label' => 'Numero da OS', 'modules' => 'Cobranca'],
        ];
    }

    public static function defaultProcessTemplate(): string
    {
        return implode("\n", [
            'Ola, {{cliente_nome}}.',
            'Foi registrado um novo andamento no processo {{processo_numero}} do condominio {{condominio_nome}}.',
            '',
            'Unidade: {{unidade_numero}}',
            'Bloco: {{bloco_nome}}',
            'Atualizacao: {{ultimo_andamento}}',
            'Data: {{andamento_data}}',
        ]);
    }

    public static function defaultCollectionTemplate(): string
    {
        return implode("\n", [
            'Ola, {{devedor_nome}}.',
            'Identificamos pendencias vinculadas ao condominio {{condominio_nome}}.',
            '',
            'Unidade: {{unidade_numero}}',
            'Bloco: {{bloco_nome}}',
            'Vencimento(s): {{vencimento}}',
            'OS: {{os_numero}}',
        ]);
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
        $this->assertConfigured($settings);

        $normalizedNumber = $this->normalizePhoneNumber($number);
        if ($normalizedNumber === '' || strlen($normalizedNumber) < 10) {
            throw new \RuntimeException('Informe um numero de teste com DDI, usando apenas numeros.');
        }

        $text = trim($message);
        if ($text === '') {
            throw new \RuntimeException('Informe a mensagem que sera enviada no teste.');
        }

        $response = $this->request($settings)->post('/message/sendText/' . rawurlencode((string) $settings['evolution_instance_name']), [
            'number' => $normalizedNumber,
            'text' => $text,
            'delay' => max(0, (int) ($settings['evolution_message_dispatch_delay_ms'] ?? static::defaultDispatchDelayMs())),
            'linkPreview' => false,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException($this->extractErrorMessage($response, 'Nao foi possivel enviar a mensagem de teste pela EvolutionAPI.'));
        }

        $payload = $this->decodeArray($response, 'Resposta invalida ao enviar a mensagem de teste.');

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

    private function normalizePhoneNumber(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
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
