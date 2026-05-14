<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, ?string $event = null): JsonResponse
    {
        $expectedToken = trim((string) AppSetting::getValue('evolution_webhook_token', ''));
        $providedToken = trim($token) !== '' ? trim($token) : trim((string) $request->header('X-Webhook-Token', ''));

        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook token invalido.',
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'event' => $event,
            'received_at' => now()->toIso8601String(),
        ]);
    }
}
