<?php

namespace App\Support\Mobile;

use App\Models\ClientCondominium;
use App\Models\ClientPortalApiToken;
use App\Models\ClientPortalUser;
use Illuminate\Http\Request;

class MobileApiContext
{
    public const USER_ATTRIBUTE = 'mobile_api_user';
    public const TOKEN_ATTRIBUTE = 'mobile_api_token';
    public const SELECTED_CONDOMINIUM_ATTRIBUTE = 'mobile_api_selected_condominium_id';

    public static function user(Request $request): ?ClientPortalUser
    {
        $user = $request->attributes->get(self::USER_ATTRIBUTE);

        return $user instanceof ClientPortalUser ? $user : null;
    }

    public static function token(Request $request): ?ClientPortalApiToken
    {
        $token = $request->attributes->get(self::TOKEN_ATTRIBUTE);

        return $token instanceof ClientPortalApiToken ? $token : null;
    }

    public static function selectedCondominiumId(Request $request): ?int
    {
        $value = $request->attributes->get(self::SELECTED_CONDOMINIUM_ATTRIBUTE);
        $selected = is_numeric($value) ? (int) $value : 0;

        return $selected > 0 ? $selected : null;
    }

    public static function selectedCondominium(Request $request): ?ClientCondominium
    {
        $user = self::user($request);
        $selectedId = self::selectedCondominiumId($request);

        if (!$user || !$selectedId) {
            return null;
        }

        return $user->accessibleCondominiums()->firstWhere('id', $selectedId);
    }
}
