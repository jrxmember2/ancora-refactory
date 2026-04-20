<?php

namespace App\Support;

use App\Models\ClientPortalUser;
use Illuminate\Http\Request;

class ClientPortalAuth
{
    public const SESSION_KEY = 'client_portal_user';

    public static function user(Request $request): ?ClientPortalUser
    {
        if (!self::hasSessionStore($request)) {
            return null;
        }

        $auth = $request->session()->get(self::SESSION_KEY);
        if (!$auth || empty($auth['id'])) {
            return null;
        }

        return ClientPortalUser::query()->active()->find((int) $auth['id']);
    }

    public static function cacheSessionUser(Request $request, ClientPortalUser $user): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'login_key' => $user->login_key,
            'email' => $user->email,
            'client_entity_id' => $user->client_entity_id,
            'client_condominium_id' => $user->client_condominium_id,
            'must_change_password' => (bool) $user->must_change_password,
            'permissions' => [
                'can_view_processes' => (bool) $user->can_view_processes,
                'can_view_cobrancas' => (bool) $user->can_view_cobrancas,
                'can_open_demands' => (bool) $user->can_open_demands,
                'can_view_demands' => (bool) $user->can_view_demands,
                'can_view_documents' => (bool) $user->can_view_documents,
                'can_view_financial_summary' => (bool) $user->can_view_financial_summary,
            ],
        ];

        if (self::hasSessionStore($request)) {
            $request->session()->put(self::SESSION_KEY, $payload);
        }

        return $payload;
    }

    public static function hasPermission(Request $request, string $permission): bool
    {
        $user = self::user($request);

        return $user ? $user->canPortal($permission) : false;
    }

    private static function hasSessionStore(Request $request): bool
    {
        return method_exists($request, 'hasSession') && $request->hasSession();
    }
}
