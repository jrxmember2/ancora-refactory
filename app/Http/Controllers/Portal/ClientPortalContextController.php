<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\ClientPortalAuth;
use App\Support\ClientPortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClientPortalContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user, 401);

        $value = trim((string) $request->input('client_condominium_id', ''));
        ClientPortalContext::select($request, $user, $value === 'all' ? null : (int) $value);

        return back()->with('success', $value === 'all' ? 'Visualizacao geral selecionada.' : 'Condominio selecionado no portal.');
    }
}
