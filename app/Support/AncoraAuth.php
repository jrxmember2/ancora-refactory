<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class AncoraAuth
{
    public const STANDARD_SESSION_MINUTES = 30;
    public const REMEMBERED_SESSION_MINUTES = 720;

    public static function user(Request $request): ?User
    {
        if (!self::hasSessionStore($request)) {
            return null;
        }

        $auth = $request->session()->get('auth_user');
        if (!$auth || empty($auth['id'])) {
            return null;
        }

        return User::query()->find((int) $auth['id']);
    }

    public static function hasModule(Request $request, string $slug): bool
    {
        if (!self::hasSessionStore($request)) {
            return false;
        }

        $auth = $request->session()->get('auth_user');
        if (!$auth) {
            return false;
        }

        if (($auth['role'] ?? null) === 'superadmin') {
            return true;
        }

        return in_array($slug, $auth['module_permissions'] ?? [], true);
    }

    public static function cacheSessionUser(Request $request, User $user, ?int $sessionMinutes = null): array
    {
        $minutes = max(5, $sessionMinutes ?? self::sessionMinutes($request));

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'theme_preference' => $user->theme_preference ?: 'dark',
            'avatar_path' => $user->avatar_path,
            'module_permissions' => $user->accessibleModuleSlugs(),
            'route_permissions' => $user->accessibleRouteNames(),
            'session_minutes' => $minutes,
            'last_interaction_at' => now()->toDateTimeString(),
            'activity_touched_at' => now()->toDateTimeString(),
        ];

        if (self::hasSessionStore($request)) {
            $request->session()->put('auth_user', $payload);
        }

        return $payload;
    }

    public static function hasActiveSession(Request $request): bool
    {
        if (!self::hasSessionStore($request) || !$request->session()->has('auth_user')) {
            return false;
        }

        return !self::sessionExpired($request);
    }

    public static function clearSession(Request $request): void
    {
        if (!self::hasSessionStore($request)) {
            return;
        }

        $request->session()->forget('auth_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public static function sessionExpired(Request $request): bool
    {
        if (!self::hasSessionStore($request)) {
            return true;
        }

        $auth = $request->session()->get('auth_user');
        if (!$auth || empty($auth['id'])) {
            return true;
        }

        $lastInteraction = trim((string) ($auth['last_interaction_at'] ?? ''));
        if ($lastInteraction === '') {
            return false;
        }

        try {
            return now()->diffInMinutes($lastInteraction) >= self::sessionMinutes($request);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function sessionMinutes(Request $request): int
    {
        if (!self::hasSessionStore($request)) {
            return self::STANDARD_SESSION_MINUTES;
        }

        return max(5, (int) $request->session()->get('auth_user.session_minutes', self::STANDARD_SESSION_MINUTES));
    }

    public static function standardSessionMinutes(): int
    {
        return self::STANDARD_SESSION_MINUTES;
    }

    public static function rememberedSessionMinutes(): int
    {
        return self::REMEMBERED_SESSION_MINUTES;
    }

    private static function hasSessionStore(Request $request): bool
    {
        return method_exists($request, 'hasSession') && $request->hasSession();
    }
}
