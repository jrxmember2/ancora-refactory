<?php

namespace App\Http\Controllers\Api\Hub;

use App\Http\Controllers\Controller;
use App\Support\AncoraSettings;
use Illuminate\Http\JsonResponse;

class HubInstanceController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => 'Âncora',
            'api' => 'hub',
            'version' => 'v1',
            'timestamp' => now()->toAtomString(),
        ]);
    }

    public function instanceInfo(): JsonResponse
    {
        try {
            $brand = AncoraSettings::brand();
            $baseUrl = rtrim((string) ($brand['base_url'] ?? config('app.url')), '/');
            $logoPath = trim((string) ($brand['logo_light'] ?? $brand['favicon'] ?? '/imgs/logomarca.svg'));

            return response()->json([
                'success' => true,
                'app' => 'Âncora',
                'api' => 'hub',
                'version' => 'v1',
                'instance_name' => trim((string) ($brand['company_name'] ?? $brand['app_name'] ?? 'Âncora')) ?: 'Âncora',
                'base_url' => $baseUrl,
                'api_base_path' => '/api/hub/v1',
                'logo_url' => $this->absoluteAssetUrl($logoPath, $baseUrl),
                'primary_color' => '#941415',
                'support_email' => trim((string) ($brand['company_email'] ?? '')),
                'support_phone' => trim((string) ($brand['company_phone'] ?? '')),
                'timestamp' => now()->toAtomString(),
            ]);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível obter as informações desta instância agora.',
            ], 500);
        }
    }

    private function absoluteAssetUrl(string $path, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
