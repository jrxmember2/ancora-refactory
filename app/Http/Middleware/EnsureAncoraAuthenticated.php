<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAncoraAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('auth_user')) {
            return redirect()->route('login')->with('error', 'Faça login para continuar.');
        }

        return $next($request);
    }
}
