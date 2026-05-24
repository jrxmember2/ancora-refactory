<?php

namespace App\Services\Mobile;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FirebaseCloudMessagingService
{
    public function enabled(): bool
    {
        return (bool) config('services.fcm.enabled')
            && trim((string) config('services.fcm.project_id')) !== ''
            && is_array($this->serviceAccount());
    }

    public function sendToToken(string $token, array $notification, array $data = []): array
    {
        return $this->sendMessageToToken($token, $notification, $data, [
            'priority' => 'HIGH',
            'channel_id' => 'ancora_clientes_updates',
            'click_action' => 'OPEN_APP',
        ]);
    }

    public function sendHubNotificationToToken(string $token, array $notification, array $data = []): array
    {
        $type = strtolower(trim((string) ($data['type'] ?? '')));
        $importantTypes = [
            'acordo_vencido',
            'conta_vencida',
            'contrato_pendente',
            'nova_demanda',
            'novo_andamento_processual',
        ];

        return $this->sendMessageToToken($token, $notification, $data, [
            'priority' => 'HIGH',
            'channel_id' => in_array($type, $importantTypes, true)
                ? 'ancora_hub_important'
                : 'ancora_hub_general',
            'click_action' => 'OPEN_APP',
        ]);
    }

    public function sendDataMessageToToken(string $token, array $data = []): array
    {
        return $this->sendMessageToToken($token, null, $data, [
            'priority' => 'HIGH',
        ]);
    }

    private function sendMessageToToken(string $token, ?array $notification, array $data, array $androidOptions): array
    {
        if (!$this->enabled()) {
            return [
                'ok' => false,
                'reason' => 'fcm_disabled',
                'message' => 'Firebase Cloud Messaging nao configurado.',
                'invalid_token' => false,
            ];
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return [
                'ok' => false,
                'reason' => 'missing_access_token',
                'message' => 'Nao foi possivel autenticar no Firebase Cloud Messaging.',
                'invalid_token' => false,
            ];
        }

        $projectId = trim((string) config('services.fcm.project_id'));
        $message = [
            'token' => $token,
            'data' => $this->normalizeData($data),
            'android' => [
                'priority' => (string) ($androidOptions['priority'] ?? 'HIGH'),
            ],
        ];

        if (is_array($notification)) {
            $message['notification'] = [
                'title' => (string) ($notification['title'] ?? ''),
                'body' => (string) ($notification['body'] ?? ''),
            ];
        }

        if (!empty($androidOptions['channel_id']) || !empty($androidOptions['click_action'])) {
            $message['android']['notification'] = array_filter([
                'channel_id' => (string) ($androidOptions['channel_id'] ?? ''),
                'click_action' => (string) ($androidOptions['click_action'] ?? ''),
            ], fn ($value) => trim((string) $value) !== '');
        }

        $payload = ['message' => $message];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

        $json = $response->json();
        $invalidToken = $this->responseIndicatesInvalidToken($json);

        return [
            'ok' => $response->successful(),
            'message' => $response->successful()
                ? 'Notificacao enviada com sucesso.'
                : trim((string) data_get($json, 'error.message', 'Falha ao enviar notificacao.')),
            'response' => $json,
            'invalid_token' => $invalidToken,
            'reason' => $response->successful() ? null : 'send_failed',
        ];
    }

    private function accessToken(): ?string
    {
        $cacheKey = 'mobile-fcm-access-token';
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $serviceAccount = $this->serviceAccount();
        if (!is_array($serviceAccount)) {
            return null;
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = $this->base64UrlEncode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $signingInput = $jwtHeader . '.' . $jwtClaim;

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, (string) $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
        if (!$signed) {
            return null;
        }

        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();
        $accessToken = trim((string) data_get($payload, 'access_token'));
        $expiresIn = max(60, (int) data_get($payload, 'expires_in', 3600) - 120);

        if ($accessToken !== '') {
            Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));
        }

        return $accessToken !== '' ? $accessToken : null;
    }

    private function serviceAccount(): ?array
    {
        static $decoded = null;

        if ($decoded !== null) {
            return $decoded;
        }

        $raw = trim((string) config('services.fcm.service_account_json_base64'));
        if ($raw === '') {
            return $decoded = null;
        }

        $json = base64_decode($raw, true);
        if (!$json) {
            return $decoded = null;
        }

        $parsed = json_decode($json, true);
        if (!is_array($parsed) || empty($parsed['client_email']) || empty($parsed['private_key'])) {
            return $decoded = null;
        }

        return $decoded = $parsed;
    }

    private function normalizeData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value)])
            ->all();
    }

    private function responseIndicatesInvalidToken(mixed $payload): bool
    {
        if (is_array($payload)) {
            $details = data_get($payload, 'error.details', []);
            if (is_array($details)) {
                foreach ($details as $detail) {
                    $errorCode = strtoupper(trim((string) data_get($detail, 'errorCode', '')));
                    if (in_array($errorCode, ['UNREGISTERED', 'REGISTRATION_TOKEN_NOT_REGISTERED'], true)) {
                        return true;
                    }
                }
            }
        }

        $string = json_encode($payload);
        if (!is_string($string)) {
            return false;
        }

        $normalized = strtolower($string);

        return str_contains($normalized, 'unregistered')
            || str_contains($normalized, 'registration-token-not-registered')
            || str_contains($normalized, 'invalid registration token')
            || str_contains($normalized, 'requested entity was not found');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
