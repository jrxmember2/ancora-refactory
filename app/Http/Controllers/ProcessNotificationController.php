<?php

namespace App\Http\Controllers;

use App\Support\AncoraAuth;
use App\Support\ProcessMovementNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcessNotificationController extends Controller
{
    public function acknowledge(Request $request, ProcessMovementNotifier $notifier): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        if ($user) {
            $notifier->acknowledge($user);
        }

        return back()->with('success', 'Movimentacoes de processos marcadas como cientes.');
    }
}
