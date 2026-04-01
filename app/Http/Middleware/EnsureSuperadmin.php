<?php

namespace App\Http\Middleware;

use App\Support\AncoraAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AncoraAuth::user($request);

        if (!$user || !$user->isSuperadmin()) {
            abort(403, 'Você não possui permissão para acessar esta área.');
        }

        return $next($request);
    }
}
