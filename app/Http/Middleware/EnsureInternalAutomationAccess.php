<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalAutomationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('automation.internal_api.token', '');
        $headerName = (string) config('automation.internal_api.token_header', 'X-Integration-Token');

        if ($token === '') {
            return $this->json('A integracao interna de automacao nao esta configurada.', 503);
        }

        $providedToken = $this->providedToken($request, $headerName);
        if ($providedToken === '' || !hash_equals($token, $providedToken)) {
            return $this->json('Nao autorizado.', 401);
        }

        $allowedIps = (array) config('automation.internal_api.allowed_ips', []);
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
