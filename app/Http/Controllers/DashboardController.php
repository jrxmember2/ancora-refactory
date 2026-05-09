<?php

namespace App\Http\Controllers;

use App\Services\HubExecutiveDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly HubExecutiveDashboardService $executiveDashboard)
    {
    }

    public function index(Request $request): View
    {
        return view('pages.admin.dashboard', [
            'title' => 'Dashboard Executivo',
            'executiveDashboard' => $this->executiveDashboard->build($request),
        ]);
    }
}
