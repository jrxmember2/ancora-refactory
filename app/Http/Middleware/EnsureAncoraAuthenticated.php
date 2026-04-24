<?php

namespace App\Http\Middleware;

use App\Support\AncoraAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAncoraAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AncoraAuth::hasActiveSession($request)) {
            AncoraAuth::clearSession($request);

            return redirect()->route('login')->with('error', 'Faça login para continuar.');
        }

        return $next($request);
    }
}
