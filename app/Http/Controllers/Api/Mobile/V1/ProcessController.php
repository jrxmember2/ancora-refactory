<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\ProcessCase;
use App\Models\ProcessCaseOption;
use App\Support\ClientPortalAccess;
use App\Support\Mobile\MobileApiContext;
use App\Support\Mobile\MobileApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessController extends Controller
{
    public function index(Request $request, ClientPortalAccess $access): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && $user->can_view_processes, 403);

        $selectedCondominiumId = MobileApiContext::selectedCondominiumId($request);
        $requestedCondominiumId = (int) $request->integer('client_condominium_id');
        if ($requestedCondominiumId > 0 && in_array($requestedCondominiumId, $user->accessibleCondominiumIds(), true)) {
            $selectedCondominiumId = $requestedCondominiumId;
        }

        $query = $access->scopeProcesses(ProcessCase::query(), $user, $selectedCondominiumId)
            ->where('is_private', false)
            ->with([
                'statusOption',
                'processTypeOption',
                'natureOption',
                'client',
                'clientCondominium',
                'adverse',
                'adverseCondominium',
                'phases' => fn ($phase) => $phase->where('is_private', false),
            ]);

        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function ($inner) use ($term) {
                $inner->where('process_number', 'like', "%{$term}%")
                    ->orWhere('client_name_snapshot', 'like', "%{$term}%")
                    ->orWhere('adverse_name', 'like', "%{$term}%");
            });
        }

        if ($status = (int) $request->integer('status_option_id')) {
            $query->where('status_option_id', $status);
        }

        $items = $query->latest('updated_at')->paginate(min(30, max(1, (int) $request->integer('per_page', 15))));

        return response()->json([
            'items' => collect($items->items())->map(fn (ProcessCase $case) => MobileApiPresenter::processSummary($case))->values()->all(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'statuses' => ProcessCaseOption::query()
                ->where('group_key', 'status')
                ->active()
                ->get()
                ->map(fn (ProcessCaseOption $option) => [
                    'id' => (int) $option->id,
                    'name' => (string) $option->name,
                    'color' => $option->color_hex ? (string) $option->color_hex : null,
                ])->values()->all(),
        ]);
    }

    public function show(Request $request, ProcessCase $process, ClientPortalAccess $access): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && $access->canSeeProcess($user, $process) && !$process->is_private, 404);

        $process->load([
            'statusOption',
            'processTypeOption',
            'natureOption',
            'client',
            'clientCondominium',
            'adverse',
            'adverseCondominium',
            'phases' => fn ($query) => $query->where('is_private', false)->latest('phase_date')->latest('created_at'),
        ]);

        return response()->json([
            'item' => MobileApiPresenter::processDetail($process),
        ]);
    }
}
