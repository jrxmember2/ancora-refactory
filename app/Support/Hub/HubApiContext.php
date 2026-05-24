<?php

namespace App\Support\Hub;

use App\Models\HubApiToken;
use App\Models\User;
use Illuminate\Http\Request;

class HubApiContext
{
    public const USER_ATTRIBUTE = 'hub_api_user';
    public const TOKEN_ATTRIBUTE = 'hub_api_token';

    public static function user(Request $request): ?User
    {
        $user = $request->attributes->get(self::USER_ATTRIBUTE);

        return $user instanceof User ? $user : null;
    }

    public static function token(Request $request): ?HubApiToken
    {
        $token = $request->attributes->get(self::TOKEN_ATTRIBUTE);

        return $token instanceof HubApiToken ? $token : null;
    }
}
