<?php

namespace App\Http\Middleware;

use App\Support\Hub\HubApiContext;
use App\Support\Hub\HubApiTokenManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHubApiAuthenticated
{
    public function __construct(
        private readonly HubApiTokenManager $tokenManager,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokenManager->findFromRequest($request);
        $user = $token?->user;

        if ($this->tokenManager->isRejected($token, $user)) {
            return $this->unauthorizedResponse();
        }

        $this->tokenManager->touch($token, $request);

        $user->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set(
            HubApiContext::TOKEN_ATTRIBUTE,
            $token->fresh(),
        );
        $request->attributes->set(
            HubApiContext::USER_ATTRIBUTE,
            $user->fresh([
                'modules' => fn ($query) => $query->where('is_enabled', 1)->orderBy('sort_order')->orderBy('name'),
                'routePermissions' => fn ($query) => $query->orderBy('route_permissions.group_key')->orderBy('route_permissions.route_name'),
            ]),
        );

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Sessão inválida ou expirada.',
        ], 401);
    }
}
