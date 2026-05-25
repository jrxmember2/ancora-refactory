<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\Proposal;
use App\Models\Servico;
use App\Models\StatusRetorno;
use App\Support\Hub\HubModulePresenter;
use App\Support\Hub\HubOfficePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['propostas.index'],
            moduleSlugs: ['propostas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->tableExists('propostas')) {
            return response()->json([
                'items' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
                'filters' => [
                    'statuses' => [],
                    'services' => [],
                ],
            ]);
        }

        $query = Proposal::query()
            ->with(['administradora', 'servico', 'statusRetorno'])
            ->withCount('attachments');

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $builder) use ($term) {
                $builder->where('proposal_code', 'like', '%' . $term . '%')
                    ->orWhere('client_name', 'like', '%' . $term . '%')
                    ->orWhere('requester_name', 'like', '%' . $term . '%')
                    ->orWhere('contact_email', 'like', '%' . $term . '%');
            });
        }

        if ((int) $request->integer('status_id') > 0) {
            $query->where('response_status_id', (int) $request->integer('status_id'));
        }

        if ((int) $request->integer('service_id') > 0) {
            $query->where('service_id', (int) $request->integer('service_id'));
        }

        $items = $query
            ->orderByDesc('proposal_date')
            ->orderByDesc('id')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (Proposal $proposal) => HubOfficePresenter::proposalSummary($proposal))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'statuses' => $this->statusOptions(),
                'services' => $this->serviceOptions(),
            ],
        ]);
    }

    public function show(Request $request, Proposal $proposal): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['propostas.show', 'propostas.index'],
            moduleSlugs: ['propostas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $proposal->load([
            'administradora',
            'servico',
            'formaEnvio',
            'statusRetorno',
            'attachments',
            'history',
        ]);

        return response()->json([
            'item' => HubOfficePresenter::proposalDetail($proposal),
        ]);
    }

    private function statusOptions(): array
    {
        if (!$this->tableExists('status_retorno')) {
            return [];
        }

        return StatusRetorno::query()
            ->active()
            ->get(['id', 'name', 'color_hex'])
            ->map(fn (StatusRetorno $status) => [
                'value' => (string) $status->id,
                'label' => (string) $status->name,
                'color' => $status->color_hex ? (string) $status->color_hex : null,
            ])
            ->values()
            ->all();
    }

    private function serviceOptions(): array
    {
        if (!$this->tableExists('servicos')) {
            return [];
        }

        return Servico::query()
            ->active()
            ->get(['id', 'name'])
            ->map(fn (Servico $service) => [
                'value' => (string) $service->id,
                'label' => (string) $service->name,
            ])
            ->values()
            ->all();
    }
}
