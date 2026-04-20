<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\ClientPortalAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClientPortalAccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('portal.account', [
            'title' => 'Minha conta',
            'portalUser' => ClientPortalAuth::user($request),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!password_verify($validated['current_password'], $user->password_hash)) {
            return back()->with('error', 'Senha atual incorreta.');
        }

        $user->forceFill([
            'password_hash' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        ClientPortalAuth::cacheSessionUser($request, $user);

        return back()->with('success', 'Senha alterada com sucesso.');
    }
}
