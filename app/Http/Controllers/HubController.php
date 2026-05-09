<?php

namespace App\Http\Controllers;

use App\Services\HubExecutiveDashboardService;
use App\Support\AncoraAuth;
use App\Support\AncoraMenu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HubController extends Controller
{
    public function __construct(private readonly HubExecutiveDashboardService $executiveDashboard)
    {
    }

    public function index(Request $request): View
    {
        $user = AncoraAuth::user($request);

        return view('pages.hub.index', [
            'title' => 'Hub',
            'tiles' => AncoraMenu::hubTiles($user),
            'executiveDashboard' => $this->executiveDashboard->build($request),
        ]);
    }
}
