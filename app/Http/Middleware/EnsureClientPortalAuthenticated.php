<?php

namespace App\Http\Middleware;

use App\Support\ClientPortalAuth;
use App\Support\ClientPortalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortalAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = ClientPortalAuth::user($request);
        if (!$user) {
            return redirect()->route('portal.login')->with('error', 'Entre no portal para continuar.');
        }

        if ($user->must_change_password && !$request->routeIs('portal.password.*', 'portal.logout')) {
            return redirect()->route('portal.password.edit')->with('error', 'Atualize sua senha para continuar.');
        }

        view()->share('clientPortalUser', $user);
        view()->share('clientPortalCondominiums', $user->accessibleCondominiums());
        view()->share('clientPortalSelectedCondominiumId', ClientPortalContext::selectedCondominiumId($request, $user));
        view()->share('clientPortalSelectedCondominium', ClientPortalContext::selectedCondominium($request, $user));

        return $next($request);
    }
}
