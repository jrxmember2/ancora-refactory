<?php

namespace App\Http\Middleware;

use App\Support\Mobile\ClientPortalApiTokenManager;
use App\Support\Mobile\MobileApiContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileApiAuthenticated
{
    public function __construct(
        private readonly ClientPortalApiTokenManager $tokenManager,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokenManager->findFromRequest($request);
        $user = $token?->portalUser;

        if (!$token || !$token->isActive() || !$user || !$user->is_active) {
            return $this->unauthorizedResponse();
        }

        $this->tokenManager->touch($token, $request);

        $request->attributes->set(MobileApiContext::TOKEN_ATTRIBUTE, $token);
        $request->attributes->set(MobileApiContext::USER_ATTRIBUTE, $user);
        $request->attributes->set(
            MobileApiContext::SELECTED_CONDOMINIUM_ATTRIBUTE,
            $this->tokenManager->selectedCondominiumId($token, $user)
        );

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Sessao invalida ou expirada.',
        ], 401);
    }
}
