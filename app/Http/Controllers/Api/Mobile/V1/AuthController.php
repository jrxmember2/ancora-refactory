<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalDeviceToken;
use App\Models\ClientPortalUser;
use App\Support\Mobile\ClientPortalApiTokenManager;
use App\Support\Mobile\MobileApiContext;
use App\Support\Mobile\MobileApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly ClientPortalApiTokenManager $tokenManager,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:160'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        $login = trim((string) $validated['login']);
        $user = ClientPortalUser::query()
            ->active()
            ->where(function ($query) use ($login) {
                $query->where('login_key', $login)
                    ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($login)]);
            })
            ->with(['condominiums', 'condominium'])
            ->first();

        if (!$user || !Hash::check((string) $validated['password'], (string) $user->password_hash)) {
            return response()->json([
                'message' => 'Chave de acesso, e-mail ou senha invalidos.',
            ], 422);
        }

        $selectedCondominiumId = null;
        $accessibleCondominiumIds = $user->accessibleCondominiumIds();
        if (count($accessibleCondominiumIds) === 1) {
            $selectedCondominiumId = $accessibleCondominiumIds[0];
        }

        $issued = $this->tokenManager->issue($user, $selectedCondominiumId, [
            'name' => 'ancora-clientes-android',
            'platform' => $validated['platform'] ?? 'android',
            'device_name' => $validated['device_name'] ?? null,
            'app_version' => $validated['app_version'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $issued['token'];
        $selectedCondominium = $selectedCondominiumId
            ? $user->accessibleCondominiums()->firstWhere('id', $selectedCondominiumId)
            : null;

        return response()->json(MobileApiPresenter::authPayload(
            user: $user->fresh(['condominiums', 'condominium']),
            plainTextToken: (string) $issued['plain_text_token'],
            selectedCondominium: $selectedCondominium,
            expiresAt: $token->expires_at,
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        $token = MobileApiContext::token($request);
        abort_unless($user && $token, 401);

        ClientPortalDeviceToken::query()
            ->where('client_portal_api_token_id', $token->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        $this->tokenManager->revoke($token);

        return response()->json([
            'ok' => true,
            'message' => 'Sessao encerrada com sucesso.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        $token = MobileApiContext::token($request);
        abort_unless($user && $token, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check((string) $validated['current_password'], (string) $user->password_hash)) {
            return response()->json([
                'message' => 'A senha atual informada nao confere.',
            ], 422);
        }

        $user->forceFill([
            'password_hash' => Hash::make((string) $validated['password']),
            'must_change_password' => false,
        ])->save();

        $this->tokenManager->revokeOtherTokens($user, $token);

        return response()->json([
            'ok' => true,
            'message' => 'Senha atualizada com sucesso.',
            'user' => MobileApiPresenter::user($user->fresh(['condominiums', 'condominium']), MobileApiContext::selectedCondominium($request)),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user, 401);

        return response()->json([
            'user' => MobileApiPresenter::user($user->fresh(['condominiums', 'condominium']), MobileApiContext::selectedCondominium($request)),
        ]);
    }
}
