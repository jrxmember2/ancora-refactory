<?php

namespace App\Http\Middleware;

use App\Support\AncoraAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AncoraAuth::hasActiveSession($request)) {
            return redirect()->route('hub');
        }

        if ($request->session()->has('auth_user')) {
            AncoraAuth::clearSession($request);
        }

        return $next($request);
    }
}
