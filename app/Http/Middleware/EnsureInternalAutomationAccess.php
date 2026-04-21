<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalAutomationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) AppSetting::getValue('automation_internal_api_token', ''));
        if ($token === '') {
            $token = trim((string) config('automation.internal_api.token', ''));
        }

        $headerName = trim((string) AppSetting::getValue('automation_internal_api_token_header', ''));
        if ($headerName === '') {
            $headerName = (string) config('automation.internal_api.token_header', 'X-Integration-Token');
        }

        if ($token === '') {
            return $this->json('A integracao interna de automacao nao esta configurada.', 503);
        }

        $providedToken = $this->providedToken($request, $headerName);
        if ($providedToken === '' || !hash_equals($token, $providedToken)) {
            return $this->json('Nao autorizado.', 401);
        }

        $allowedIps = $this->allowedIps();
        if ($allowedIps !== [] && !in_array((string) $request->ip(), $allowedIps, true)) {
            return $this->json('IP nao permitido para esta integracao.', 403);
        }

        return $next($request);
    }

    private function providedToken(Request $request, string $headerName): string
    {
        $bearer = trim((string) $request->bearerToken());
        if ($bearer !== '') {
            return $bearer;
        }

        return trim((string) $request->header($headerName, ''));
    }

    private function allowedIps(): array
    {
        $stored = trim((string) AppSetting::getValue('automation_internal_api_allowed_ips', ''));

        if ($stored !== '') {
            return $this->parseAllowedIps($stored);
        }

        return array_values(array_filter(array_map(
            static fn ($ip) => trim((string) $ip),
            (array) config('automation.internal_api.allowed_ips', [])
        )));
    }

    private function parseAllowedIps(string $value): array
    {
        $items = preg_split('/[\r\n,;]+/', $value) ?: [];
        $normalized = [];

        foreach ($items as $item) {
            $ip = trim($item);
            if ($ip === '') {
                continue;
            }

            $normalized[$ip] = $ip;
        }

        return array_values($normalized);
    }

    private function json(string $message, int $status): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => [
                'message' => $message,
            ],
        ], $status);
    }
}
