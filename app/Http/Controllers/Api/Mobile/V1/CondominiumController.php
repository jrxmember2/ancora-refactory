<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Support\Mobile\ClientPortalApiTokenManager;
use App\Support\Mobile\MobileApiContext;
use App\Support\Mobile\MobileApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CondominiumController extends Controller
{
    public function __construct(
        private readonly ClientPortalApiTokenManager $tokenManager,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user, 401);

        return response()->json([
            'selected_condominium' => MobileApiContext::selectedCondominium($request)
                ? MobileApiPresenter::condominium(MobileApiContext::selectedCondominium($request))
                : null,
            'items' => MobileApiPresenter::condominiums($user->accessibleCondominiums()),
        ]);
    }

    public function updateContext(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        $token = MobileApiContext::token($request);
        abort_unless($user && $token, 401);

        $validated = $request->validate([
            'client_condominium_id' => ['nullable', 'integer'],
        ]);

        $selectedId = $this->tokenManager->updateSelectedCondominium(
            token: $token,
            user: $user,
            condominiumId: !empty($validated['client_condominium_id']) ? (int) $validated['client_condominium_id'] : null,
        );

        if (!empty($validated['client_condominium_id']) && !$selectedId) {
            return response()->json([
                'message' => 'O condominio informado nao esta disponivel para este usuario.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'selected_condominium' => $selectedId
                ? MobileApiPresenter::condominium($user->accessibleCondominiums()->firstWhere('id', $selectedId))
                : null,
        ]);
    }
}
