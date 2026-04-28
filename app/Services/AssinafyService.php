<?php

namespace App\Services;

use App\Support\ContractSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AssinafyService
{
    public function isConfigured(): bool
    {
        return $this->apiCredential() !== '' && $this->accountId() !== '';
    }

    public function missingConfig(): array
    {
        $missing = [];

        if ($this->apiCredential() === '') {
            $missing[] = 'API key da Assinafy';
        }

        if ($this->accountId() === '') {
            $missing[] = 'Workspace/Account ID da Assinafy';
        }

        return $missing;
    }

    public function accountId(): string
    {
        return trim((string) ContractSettings::get('assinafy_account_id', ''));
    }

    public function webhookToken(): string
    {
        return trim((string) ContractSettings::get('assinafy_webhook_token', ''));
    }

    public function webhookUrl(): string
    {
        return route('integrations.assinafy.webhook', ['token' => $this->webhookToken()], true);
    }

    public function defaultEvents(): array
    {
        return [
            'assignment_created',
            'signature_requested',
            'signer_viewed_document',
            'signer_signed_document',
            'signer_rejected_document',
            'document_ready',
            'document_processing_failed',
        ];
    }

    public function syncWebhookSubscription(): array
    {
        return $this->requestJson('PUT', '/accounts/' . $this->accountId() . '/webhooks/subscriptions', [
            'json' => [
                'events' => $this->defaultEvents(),
                'is_active' => true,
                'url' => $this->webhookUrl(),
                'email' => trim((string) ContractSettings::get('assinafy_webhook_email', '')) ?: null,
            ],
        ]);
    }

    public function uploadDocument(string $documentName, string $absolutePdfPath): array
    {
        if (!is_file($absolutePdfPath)) {
            throw new \RuntimeException('Arquivo PDF local nao encontrado para envio a Assinafy.');
        }

        $url = $this->baseUrl() . '/accounts/' . $this->accountId() . '/documents';

        $response = $this->request()
            ->attach('file', file_get_contents($absolutePdfPath), $documentName)
            ->post($url);

        return $this->decodeJson($response->status(), (string) $response->body());
    }

    public function findOrCreateSigner(array $payload): array
    {
        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '') {
            $found = $this->findSignerByEmail($email);
            if ($found) {
                return $found;
            }
        }

        return $this->requestJson('POST', '/accounts/' . $this->accountId() . '/signers', [
            'json' => array_filter([
                'full_name' => trim((string) ($payload['full_name'] ?? '')),
                'email' => $email !== '' ? $email : null,
                'whatsapp_phone_number' => trim((string) ($payload['whatsapp_phone_number'] ?? '')) ?: null,
                'government_id' => trim((string) ($payload['government_id'] ?? '')) ?: null,
            ], static fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    public function createAssignment(string $documentId, array $signerIds, ?string $message = null): array
    {
        $body = [
            'method' => 'virtual',
            'signerIds' => array_values($signerIds),
        ];

        if ($message !== null && trim($message) !== '') {
            $body['message'] = trim($message);
        }

        return $this->requestJson('POST', '/documents/' . $documentId . '/assignments', [
            'json' => $body,
        ]);
    }

    public function getDocument(string $documentId): array
    {
        return $this->requestJson('GET', '/documents/' . $documentId);
    }

    public function downloadArtifact(string $documentId, string $artifactName, string $targetPath): bool
    {
        $artifact = trim($artifactName);
        if (!in_array($artifact, ['original', 'certificated', 'certificate-page', 'bundle'], true)) {
            return false;
        }

        $response = $this->request()->get($this->baseUrl() . '/documents/' . $documentId . '/download/' . $artifact);
        if (!$response->successful()) {
            return false;
        }

        File::ensureDirectoryExists(dirname($targetPath));
        File::put($targetPath, $response->body());

        return is_file($targetPath) && (int) filesize($targetPath) > 0;
    }

    private function findSignerByEmail(string $email): ?array
    {
        $result = $this->requestJson('GET', '/accounts/' . $this->accountId() . '/signers', [
            'query' => [
                'search' => $email,
                'page' => 1,
                'per-page' => 100,
            ],
        ]);

        $rows = array_values(array_filter((array) $result, static fn ($item) => is_array($item)));
        foreach ($rows as $row) {
            if (Str::lower((string) ($row['email'] ?? '')) === Str::lower($email)) {
                return $row;
            }
        }

        return null;
    }

    private function requestJson(string $method, string $uri, array $options = []): array
    {
        $response = match (strtoupper($method)) {
            'GET' => $this->request()->get($this->baseUrl() . $uri, $options['query'] ?? []),
            'POST' => $this->request()->post($this->baseUrl() . $uri, $options['json'] ?? []),
            'PUT' => $this->request()->put($this->baseUrl() . $uri, $options['json'] ?? []),
            'DELETE' => $this->request()->delete($this->baseUrl() . $uri, $options['json'] ?? []),
            default => throw new \InvalidArgumentException('Metodo HTTP nao suportado na integracao Assinafy.'),
        };

        return $this->decodeJson($response->status(), (string) $response->body());
    }

    private function decodeJson(int $statusCode, string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Resposta invalida da Assinafy.');
        }

        if ($statusCode >= 400 || (int) ($decoded['status'] ?? $statusCode) >= 400) {
            $message = trim((string) ($decoded['message'] ?? 'Falha ao comunicar com a Assinafy.'));
            throw new \RuntimeException($message !== '' ? $message : 'Falha ao comunicar com a Assinafy.');
        }

        return (array) ($decoded['data'] ?? []);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(90)
            ->acceptJson()
            ->withHeaders($this->headers());
    }

    private function headers(): array
    {
        $token = trim((string) ContractSettings::get('assinafy_access_token', ''));
        if ($token !== '') {
            return [
                'Authorization' => 'Bearer ' . $token,
            ];
        }

        return [
            'X-Api-Key' => $this->apiCredential(),
        ];
    }

    private function apiCredential(): string
    {
        return trim((string) ContractSettings::get('assinafy_api_key', ''));
    }

    private function baseUrl(): string
    {
        return ContractSettings::get('assinafy_environment', 'production') === 'sandbox'
            ? 'https://sandbox.assinafy.com.br/v1'
            : 'https://api.assinafy.com.br/v1';
    }
}
