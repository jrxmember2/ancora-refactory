<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CobrancaCase;
use App\Models\Demand;
use App\Models\ProcessCase;
use App\Support\ClientPortalAccess;
use App\Support\ClientPortalAuth;
use App\Support\ClientPortalContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalDashboardController extends Controller
{
    public function __invoke(Request $request, ClientPortalAccess $access): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user, 401);

        $selectedCondominiumId = ClientPortalContext::selectedCondominiumId($request, $user);

        $processQuery = $access->scopeProcesses(ProcessCase::query(), $user, $selectedCondominiumId)->where('is_private', false);
        $cobrancaQuery = $access->scopeCobrancas(CobrancaCase::query(), $user, $selectedCondominiumId);
        $demandQuery = $access->scopeDemands(Demand::query(), $user, $selectedCondominiumId);

        $latestProcesses = $user->can_view_processes
            ? (clone $processQuery)->with(['statusOption', 'processTypeOption', 'phases' => fn ($query) => $query->where('is_private', false)->limit(1)])->latest('updated_at')->limit(4)->get()
            : collect();

        $latestCobrancas = $user->can_view_cobrancas
            ? (clone $cobrancaQuery)->with(['condominium', 'block', 'unit'])->latest('updated_at')->limit(4)->get()
            : collect();

        $latestDemands = $user->can_view_demands
            ? (clone $demandQuery)->with(['category'])->latest('updated_at')->limit(5)->get()
            : collect();

        return view('portal.dashboard', [
            'title' => 'Dashboard',
            'portalUser' => $user,
            'summary' => [
                'processes_active' => $user->can_view_processes ? (clone $processQuery)->whereNull('closed_at')->count() : 0,
                'cobrancas_active' => $user->can_view_cobrancas ? (clone $cobrancaQuery)->whereNotIn('workflow_stage', ['encerrado', 'cancelado'])->count() : 0,
                'demands_open' => $user->can_view_demands ? (clone $demandQuery)->whereNotIn('status', ['concluida', 'cancelada'])->count() : 0,
                'demands_waiting_client' => $user->can_view_demands ? (clone $demandQuery)->where('status', 'aguardando_cliente')->count() : 0,
            ],
            'latestProcesses' => $latestProcesses,
            'latestCobrancas' => $latestCobrancas,
            'latestDemands' => $latestDemands,
            'demandStatusLabels' => Demand::statusLabels(),
        ]);
    }
}
