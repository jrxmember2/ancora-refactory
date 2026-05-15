<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\EvolutionWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvolutionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        ?string $event = null,
        EvolutionWebhookService $webhookService,
    ): JsonResponse
    {
        $expectedToken = trim((string) AppSetting::getValue('evolution_webhook_token', ''));
        $providedToken = trim($token) !== '' ? trim($token) : trim((string) $request->header('X-Webhook-Token', ''));

        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook token invalido.',
            ], 403);
        }

        try {
            $result = $webhookService->handle($event, $request->all());

            return response()->json(array_merge($result, [
                'received_at' => now()->toIso8601String(),
            ]));
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'event' => $event,
                'message' => $exception->getMessage(),
                'received_at' => now()->toIso8601String(),
            ], 500);
        }
    }
}
