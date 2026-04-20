<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalUser;
use App\Support\ClientPortalAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClientPortalAuthController extends Controller
{
    public function loginForm(): View
    {
        return view('portal.auth.login', ['title' => 'Portal do Cliente']);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_key' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
        ]);

        $loginKey = trim((string) $credentials['login_key']);
        $user = ClientPortalUser::query()
            ->active()
            ->where('login_key', $loginKey)
            ->first();

        if (!$user || !password_verify($credentials['password'], $user->password_hash)) {
            return back()->withInput($request->only('login_key'))->with('error', 'Chave de acesso ou senha invalidas.');
        }

        ClientPortalAuth::cacheSessionUser($request, $user);
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->must_change_password) {
            return redirect()->route('portal.password.edit');
        }

        return redirect()->route('portal.dashboard');
    }

    public function forgotPassword(): View
    {
        return view('portal.auth.forgot-password', ['title' => 'Recuperar acesso']);
    }

    public function passwordEdit(Request $request): View
    {
        return view('portal.auth.change-password', [
            'title' => 'Atualizar senha',
            'clientPortalUser' => ClientPortalAuth::user($request),
        ]);
    }

    public function passwordUpdate(Request $request): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill([
            'password_hash' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        ClientPortalAuth::cacheSessionUser($request, $user);

        return redirect()->route('portal.dashboard')->with('success', 'Senha atualizada com sucesso.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(ClientPortalAuth::SESSION_KEY);
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('success', 'Sessao encerrada.');
    }
}
