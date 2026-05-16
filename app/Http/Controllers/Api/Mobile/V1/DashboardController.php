<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalNotification;
use App\Models\Demand;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use App\Support\ClientPortalAccess;
use App\Support\Mobile\MobileApiContext;
use App\Support\Mobile\MobileApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClientPortalAccess $access): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user, 401);

        $selectedCondominiumId = MobileApiContext::selectedCondominiumId($request);
        $processQuery = $access->scopeProcesses(ProcessCase::query(), $user, $selectedCondominiumId)
            ->where('is_private', false);
        $demandQuery = $access->scopeDemands(Demand::query(), $user, $selectedCondominiumId);

        if (!$user->can_view_demands && $user->can_open_demands) {
            $demandQuery->where('client_portal_user_id', $user->id);
        }

        $latestProcesses = $user->can_view_processes
            ? (clone $processQuery)->with([
                'statusOption',
                'processTypeOption',
                'natureOption',
                'phases' => fn ($query) => $query->where('is_private', false),
            ])->latest('updated_at')->limit(4)->get()
            : collect();

        $latestDemands = ($user->can_view_demands || $user->can_open_demands)
            ? (clone $demandQuery)->with(['category', 'tag', 'condominium'])->latest('updated_at')->limit(5)->get()
            : collect();

        $latestPublicPhases = $user->can_view_processes
            ? ProcessCasePhase::query()
                ->with('processCase')
                ->where('is_private', false)
                ->whereHas('processCase', fn (Builder $query) => $access->scopeProcesses($query, $user, $selectedCondominiumId)->where('is_private', false))
                ->latest('phase_date')
                ->latest('created_at')
                ->limit(6)
                ->get()
            : collect();

        $unreadNotifications = ClientPortalNotification::query()
            ->where('client_portal_user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'greeting' => 'Ola, ' . $user->name,
            'selected_condominium' => MobileApiContext::selectedCondominium($request)
                ? MobileApiPresenter::condominium(MobileApiContext::selectedCondominium($request))
                : null,
            'summary' => [
                'processes_active' => $user->can_view_processes ? (clone $processQuery)->whereNull('closed_at')->count() : 0,
                'demands_open' => ($user->can_view_demands || $user->can_open_demands) ? (clone $demandQuery)->whereNotIn('status', ['concluida', 'cancelada'])->count() : 0,
                'demands_waiting_client' => ($user->can_view_demands || $user->can_open_demands) ? (clone $demandQuery)->where('status', 'aguardando_cliente')->count() : 0,
                'notifications_unread' => $unreadNotifications,
            ],
            'latest_processes' => $latestProcesses->map(fn (ProcessCase $case) => MobileApiPresenter::processSummary($case))->values()->all(),
            'latest_demands' => $latestDemands->map(fn (Demand $demand) => MobileApiPresenter::demandSummary($demand))->values()->all(),
            'latest_movements' => MobileApiPresenter::recentMovements($latestPublicPhases, $latestDemands),
        ]);
    }
}
