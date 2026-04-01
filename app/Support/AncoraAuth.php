<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class AncoraAuth
{
    public static function user(Request $request): ?User
    {
        $auth = $request->session()->get('auth_user');
        if (!$auth || empty($auth['id'])) {
            return null;
        }

        return User::query()->find((int) $auth['id']);
    }

    public static function hasModule(Request $request, string $slug): bool
    {
        $auth = $request->session()->get('auth_user');
        if (!$auth) {
            return false;
        }

        if (($auth['role'] ?? null) === 'superadmin') {
            return true;
        }

        return in_array($slug, $auth['module_permissions'] ?? [], true);
    }

    public static function cacheSessionUser(Request $request, User $user): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'theme_preference' => $user->theme_preference ?: 'dark',
            'module_permissions' => $user->accessibleModuleSlugs(),
        ];

        $request->session()->put('auth_user', $payload);

        return $payload;
    }
}
