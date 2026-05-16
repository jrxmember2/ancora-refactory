<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalDeviceToken;
use App\Support\Mobile\MobileApiContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        $token = MobileApiContext::token($request);
        abort_unless($user && $token, 401);

        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'min:20'],
            'device_name' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        $hash = hash('sha256', (string) $validated['fcm_token']);
        $device = ClientPortalDeviceToken::query()->updateOrCreate(
            ['fcm_token_hash' => $hash],
            [
                'client_portal_user_id' => $user->id,
                'client_portal_api_token_id' => $token->id,
                'fcm_token' => (string) $validated['fcm_token'],
                'platform' => trim((string) ($validated['platform'] ?? 'android')) ?: 'android',
                'device_name' => trim((string) ($validated['device_name'] ?? '')) ?: null,
                'app_version' => trim((string) ($validated['app_version'] ?? '')) ?: null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return response()->json([
            'ok' => true,
            'device_id' => (int) $device->id,
        ]);
    }

    public function unregister(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        $token = MobileApiContext::token($request);
        abort_unless($user && $token, 401);

        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string'],
        ]);

        $query = ClientPortalDeviceToken::query()
            ->where('client_portal_user_id', $user->id)
            ->whereNull('revoked_at');

        if (!empty($validated['fcm_token'])) {
            $query->where('fcm_token_hash', hash('sha256', (string) $validated['fcm_token']));
        } else {
            $query->where(function ($inner) use ($token) {
                $inner->where('client_portal_api_token_id', $token->id)
                    ->orWhereNull('client_portal_api_token_id');
            });
        }

        $query->update([
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
        ]);
    }
}
