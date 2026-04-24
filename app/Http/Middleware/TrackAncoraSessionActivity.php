<?php

namespace App\Http\Middleware;

use App\Support\AncoraAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackAncoraSessionActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AncoraAuth::hasActiveSession($request)) {
            AncoraAuth::clearSession($request);

            return redirect()
                ->route('login')
                ->with('error', 'Sua sessão expirou por inatividade. Faça login novamente para continuar.');
        }

        $now = now();
        $request->session()->put('auth_user.last_interaction_at', $now->toDateTimeString());

        $lastPing = $request->session()->get('auth_user.activity_touched_at');
        $mustPingDatabase = true;

        if (is_string($lastPing) && trim($lastPing) !== '') {
            try {
                $mustPingDatabase = $now->diffInSeconds($lastPing) >= 60;
            } catch (\Throwable) {
                $mustPingDatabase = true;
            }
        }

        if ($mustPingDatabase && ($user = AncoraAuth::user($request))) {
            $user->forceFill(['last_seen_at' => $now])->save();
            $request->session()->put('auth_user.activity_touched_at', $now->toDateTimeString());
        }

        return $next($request);
    }
}
