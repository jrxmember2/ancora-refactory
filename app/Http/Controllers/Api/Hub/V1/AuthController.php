<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\HubDeviceToken;
use App\Models\User;
use App\Services\Hub\HubAppLoginLogService;
use App\Support\Hub\HubApiContext;
use App\Support\Hub\HubApiPresenter;
use App\Support\Hub\HubApiTokenManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends HubApiController
{
    public function __construct(
        private readonly HubApiTokenManager $tokenManager,
        private readonly HubAppLoginLogService $appLoginLogService,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request, [
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:160'],
            'platform' => ['nullable', 'string', 'max:20'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'biometric_enabled' => ['nullable', 'boolean'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $email = mb_strtolower(trim((string) $validated['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->with([
                'modules' => fn ($query) => $query->where('is_enabled', 1)->orderBy('sort_order')->orderBy('name'),
                'routePermissions' => fn ($query) => $query->orderBy('route_permissions.group_key')->orderBy('route_permissions.route_name'),
            ])
            ->first();

        if (!$user || !password_verify((string) $validated['password'], (string) $user->password_hash)) {
            $this->safeLogAttempt($request, $user, null, $validated, false, 'invalid_credentials');

            return response()->json([
                'message' => 'E-mail ou senha inválidos.',
            ], 422);
        }

        if (!(bool) $user->is_active) {
            $this->safeLogAttempt($request, $user, null, $validated, false, 'inactive_user');

            return response()->json([
                'message' => 'Usuário inativo ou sem acesso ao aplicativo.',
            ], 401);
        }

        $issued = $this->tokenManager->issue($user, [
            'name' => 'ancora-hub-android',
            'platform' => $validated['platform'] ?? 'android',
            'device_name' => $validated['device_name'] ?? null,
            'app_version' => $validated['app_version'] ?? null,
            'biometric_enabled' => (bool) ($validated['biometric_enabled'] ?? false),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $token = $issued['token'];

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $this->safeLogAttempt($request, $user, $token, $validated, true);

        return response()->json(HubApiPresenter::authPayload(
            user: $user->fresh(['modules', 'routePermissions']),
            plainTextToken: (string) $issued['plain_text_token'],
            expiresAt: $token->expires_at,
            sessionPolicy: $this->tokenManager->sessionPolicy((bool) $token->biometric_enabled),
        ));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);
        $token = HubApiContext::token($request);

        if (!$user || !$token) {
            return $this->unauthorizedResponse();
        }

        HubDeviceToken::query()
            ->where('hub_api_token_id', $token->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        $this->tokenManager->revoke($token);

        return response()->json([
            'ok' => true,
            'message' => 'Sessão encerrada com sucesso.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);
        $token = HubApiContext::token($request);

        if (!$user || !$token) {
            return $this->unauthorizedResponse();
        }

        $validated = $this->validateRequest($request, [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        if (!password_verify((string) $validated['current_password'], (string) $user->password_hash)) {
            return response()->json([
                'message' => 'A senha atual informada não confere.',
            ], 422);
        }

        $user->forceFill([
            'password_hash' => password_hash((string) $validated['password'], PASSWORD_DEFAULT),
        ])->save();

        $revokedTokenIds = $this->tokenManager->revokeOtherTokens($user, $token);

        if ($revokedTokenIds !== []) {
            HubDeviceToken::query()
                ->whereIn('hub_api_token_id', $revokedTokenIds)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json(array_merge([
            'ok' => true,
            'message' => 'Senha atualizada com sucesso.',
        ], HubApiPresenter::profilePayload(
            user: $user->fresh(['modules', 'routePermissions']),
            expiresAt: $token->expires_at,
            sessionPolicy: $this->tokenManager->sessionPolicy((bool) $token->biometric_enabled),
        )));
    }

    public function me(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);
        $token = HubApiContext::token($request);

        if (!$user || !$token) {
            return $this->unauthorizedResponse();
        }

        return response()->json(HubApiPresenter::profilePayload(
            user: $user->fresh(['modules', 'routePermissions']),
            expiresAt: $token->expires_at,
            sessionPolicy: $this->tokenManager->sessionPolicy((bool) $token->biometric_enabled),
        ));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = HubApiContext::user($request);
        $token = HubApiContext::token($request);

        if (!$user || !$token) {
            return $this->unauthorizedResponse();
        }

        $validated = $this->validateRequest($request, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'theme_preference' => ['nullable', Rule::in(['light', 'dark'])],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $user->forceFill([
            'name' => trim((string) $validated['name']),
            'email' => Str::lower(trim((string) $validated['email'])),
            'theme_preference' => (string) ($validated['theme_preference'] ?? ($user->theme_preference ?: 'dark')),
        ])->save();

        return response()->json(array_merge([
            'ok' => true,
            'message' => 'Perfil atualizado com sucesso.',
        ], HubApiPresenter::profilePayload(
            user: $user->fresh(['modules', 'routePermissions']),
            expiresAt: $token->expires_at,
            sessionPolicy: $this->tokenManager->sessionPolicy((bool) $token->biometric_enabled),
        )));
    }

    private function safeLogAttempt(
        Request $request,
        ?User $user,
        mixed $token,
        array $validated,
        bool $success,
        ?string $failureReason = null,
    ): void {
        try {
            $this->appLoginLogService->recordAttempt(
                user: $user,
                token: $token,
                request: $request,
                meta: [
                    'platform' => $validated['platform'] ?? 'android',
                    'device_name' => $validated['device_name'] ?? null,
                    'app_version' => $validated['app_version'] ?? null,
                ],
                success: $success,
                failureReason: $failureReason,
            );
        } catch (\Throwable) {
            //
        }
    }
}
