<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\HubDeviceToken;
use App\Support\Hub\HubApiContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends HubApiController
{
    public function register(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);
        $token = HubApiContext::token($request);

        if (!$user || !$token) {
            return $this->unauthorizedResponse();
        }

        $validated = $this->validateRequest($request, [
            'fcm_token' => ['required', 'string', 'min:20'],
            'device_name' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $hash = hash('sha256', (string) $validated['fcm_token']);

        $device = HubDeviceToken::query()->updateOrCreate(
            ['fcm_token_hash' => $hash],
            [
                'user_id' => $user->id,
                'hub_api_token_id' => $token->id,
                'fcm_token' => (string) $validated['fcm_token'],
                'platform' => trim((string) ($validated['platform'] ?? 'android')) ?: 'android',
                'device_name' => $this->nullableTrim($validated['device_name'] ?? null),
                'app_version' => $this->nullableTrim($validated['app_version'] ?? null),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => 'Dispositivo registrado com sucesso.',
            'device_id' => (int) $device->id,
        ]);
    }

    public function unregister(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);
        $token = HubApiContext::token($request);

        if (!$user || !$token) {
            return $this->unauthorizedResponse();
        }

        $validated = $this->validateRequest($request, [
            'fcm_token' => ['nullable', 'string'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $query = HubDeviceToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at');

        if (!empty($validated['fcm_token'])) {
            $query->where('fcm_token_hash', hash('sha256', (string) $validated['fcm_token']));
        } else {
            $query->where(function ($inner) use ($token) {
                $inner->where('hub_api_token_id', $token->id)
                    ->orWhereNull('hub_api_token_id');
            });
        }

        $query->update([
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Dispositivo removido com sucesso.',
        ]);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
