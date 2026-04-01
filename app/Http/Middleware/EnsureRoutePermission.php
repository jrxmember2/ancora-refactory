<?php

namespace App\Http\Middleware;

use App\Support\AncoraAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoutePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = AncoraAuth::user($request);
        if (!$user) {
            abort(401);
        }

        if ($user->isSuperadmin()) {
            return $next($request);
        }

        $routePermissions = $request->session()->get('auth_user.route_permissions', []);

        if (!in_array($permission, $routePermissions, true)) {
            abort(403, 'Você não possui permissão para executar esta ação.');
        }

        return $next($request);
    }
}
