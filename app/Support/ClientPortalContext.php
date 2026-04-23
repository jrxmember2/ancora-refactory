<?php

namespace App\Support;

use App\Models\ClientCondominium;
use App\Models\ClientPortalUser;
use Illuminate\Http\Request;

class ClientPortalContext
{
    public const SESSION_KEY = 'client_portal_selected_condominium_id';

    public static function selectedCondominiumId(Request $request, ClientPortalUser $user): ?int
    {
        if (!self::hasSessionStore($request)) {
            return null;
        }

        $selected = (int) $request->session()->get(self::SESSION_KEY, 0);
        if ($selected <= 0) {
            return null;
        }

        if (!in_array($selected, $user->accessibleCondominiumIds(), true)) {
            $request->session()->forget(self::SESSION_KEY);
            return null;
        }

        return $selected;
    }

    public static function selectedCondominium(Request $request, ClientPortalUser $user): ?ClientCondominium
    {
        $selectedId = self::selectedCondominiumId($request, $user);
        if (!$selectedId) {
            return null;
        }

        return $user->accessibleCondominiums()->firstWhere('id', $selectedId);
    }

    public static function select(Request $request, ClientPortalUser $user, ?int $condominiumId): void
    {
        if (!self::hasSessionStore($request)) {
            return;
        }

        $condominiumId = (int) ($condominiumId ?? 0);
        if ($condominiumId <= 0) {
            $request->session()->forget(self::SESSION_KEY);
            return;
        }

        if (in_array($condominiumId, $user->accessibleCondominiumIds(), true)) {
            $request->session()->put(self::SESSION_KEY, $condominiumId);
        }
    }

    private static function hasSessionStore(Request $request): bool
    {
        return method_exists($request, 'hasSession') && $request->hasSession();
    }
}
