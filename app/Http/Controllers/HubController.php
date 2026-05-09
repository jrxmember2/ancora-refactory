<?php

namespace App\Http\Controllers;

use App\Support\AncoraAuth;
use App\Support\AncoraMenu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubController extends Controller
{
    public function index(Request $request): View
    {
        $user = AncoraAuth::user($request);

        return view('pages.hub.index', [
            'title' => 'Hub',
            'tiles' => AncoraMenu::hubTiles($user),
        ]);
    }
}
