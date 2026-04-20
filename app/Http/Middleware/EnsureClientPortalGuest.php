<?php

namespace App\Http\Middleware;

use App\Support\ClientPortalAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortalGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ClientPortalAuth::user($request)) {
            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}
