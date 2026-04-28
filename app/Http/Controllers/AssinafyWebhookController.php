<?php

namespace App\Http\Controllers;

use App\Services\DocumentSignatureService;
use App\Support\ContractSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssinafyWebhookController extends Controller
{
    public function __invoke(Request $request, DocumentSignatureService $signatureService): JsonResponse
    {
        $expectedToken = trim((string) ContractSettings::get('assinafy_webhook_token', ''));
        $providedToken = trim((string) ($request->query('token') ?: $request->header('X-Webhook-Token', '')));

        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook token invalido.',
            ], 403);
        }

        $payload = $request->all();
        $matched = $signatureService->processWebhook($payload);

        return response()->json([
            'ok' => true,
            'matched' => (bool) $matched,
            'signature_request_id' => $matched?->id,
        ]);
    }
}
