<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.signin', ['title' => 'Entrar']);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember_for_12h' => ['nullable'],
        ]);

        $user = User::query()->active()->where('email', $credentials['email'])->first();
        if (!$user || !password_verify($credentials['password'], $user->password_hash)) {
            return back()->withInput()->with('error', 'E-mail ou senha inválidos.');
        }

        $request->session()->regenerate();
        $sessionMinutes = $request->boolean('remember_for_12h')
            ? AncoraAuth::rememberedSessionMinutes()
            : AncoraAuth::standardSessionMinutes();

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        AncoraAuth::cacheSessionUser($request, $user, $sessionMinutes);

        AuditLog::query()->create([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'action' => 'login',
            'entity_type' => 'users',
            'entity_id' => $user->id,
            'details' => 'Login realizado no novo core Laravel.',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        return redirect()->route('hub');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $auth = $request->session()->get('auth_user');

        if (!empty($auth['id'])) {
            User::query()->whereKey((int) $auth['id'])->update(['last_seen_at' => null]);
        }

        AncoraAuth::clearSession($request);

        if ($auth) {
            AuditLog::query()->create([
                'user_id' => $auth['id'] ?? null,
                'user_email' => $auth['email'] ?? 'desconhecido',
                'action' => 'logout',
                'entity_type' => 'users',
                'entity_id' => $auth['id'] ?? null,
                'details' => 'Logout realizado no novo core Laravel.',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        }

        return redirect()->route('login')->with('success', 'Sessão encerrada.');
    }
}
