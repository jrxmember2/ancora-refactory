<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ProcessCase;
use App\Models\ProcessCaseOption;
use App\Support\ClientPortalAccess;
use App\Support\ClientPortalAuth;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalProcessController extends Controller
{
    public function index(Request $request, ClientPortalAccess $access): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $user->can_view_processes, 403);

        $query = $access->scopeProcesses(ProcessCase::query(), $user)
            ->where('is_private', false)
            ->with(['statusOption', 'processTypeOption', 'actionTypeOption', 'phases' => fn ($phase) => $phase->where('is_private', false)->limit(1)])
            ->withMax(['phases as last_public_phase_at' => fn ($phase) => $phase->where('is_private', false)], 'phase_date');

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

        return view('portal.processes.index', [
            'title' => 'Processos',
            'items' => $query->latest('updated_at')->paginate(12)->withQueryString(),
            'filters' => $request->all(),
            'statuses' => ProcessCaseOption::query()->where('group_key', 'status')->active()->get(),
        ]);
    }

    public function show(Request $request, ProcessCase $processo, ClientPortalAccess $access): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $access->canSeeProcess($user, $processo) && !$processo->is_private, 404);

        $processo->load([
            'statusOption',
            'processTypeOption',
            'actionTypeOption',
            'natureOption',
            'phases' => fn ($query) => $query->where('is_private', false)->latest('phase_date')->latest('created_at'),
        ]);

        return view('portal.processes.show', [
            'title' => $processo->process_number ?: 'Processo',
            'case' => $processo,
        ]);
    }
}
