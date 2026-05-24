<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\ClientAttachment;
use App\Models\ClientCondominium;
use App\Models\ClientTimeline;
use App\Models\ClientUnit;
use App\Support\Hub\HubClientPresenter;
use App\Support\Hub\HubModulePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CondominiumController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['clientes.index', 'clientes.condominios'],
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = ClientCondominium::query()
            ->with(['type', 'syndic', 'administradora'])
            ->withCount('units');

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('cnpj', 'like', "%{$term}%")
                    ->orWhereHas('syndic', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"))
                    ->orWhereHas('administradora', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"));
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $items = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (ClientCondominium $condominium) => HubClientPresenter::condominiumSummary($condominium))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'statuses' => HubClientPresenter::statusOptions(),
            ],
        ]);
    }

    public function show(Request $request, ClientCondominium $condominium): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['clientes.index', 'clientes.condominios'],
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $condominium->load([
            'type',
            'syndic',
            'administradora',
        ])->loadCount('units');

        $documents = ClientAttachment::query()
            ->where('related_type', 'condominium')
            ->where('related_id', $condominium->id)
            ->latest('created_at')
            ->limit(40)
            ->get();

        $units = ClientUnit::query()
            ->with(['condominium', 'block', 'type', 'owner', 'tenant'])
            ->where('condominium_id', $condominium->id)
            ->orderBy('unit_number')
            ->limit(12)
            ->get();

        $timeline = ClientTimeline::query()
            ->where('related_type', 'condominium')
            ->where('related_id', $condominium->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'item' => HubClientPresenter::condominiumDetail(
                condominium: $condominium,
                documents: $documents->map(fn (ClientAttachment $attachment) => HubClientPresenter::document($attachment))->values()->all(),
                units: $units->map(fn (ClientUnit $unit) => HubClientPresenter::unitSummary($unit))->values()->all(),
                timeline: $timeline->map(fn (ClientTimeline $item) => HubClientPresenter::timelineItem($item))->values()->all(),
            ),
        ]);
    }

    public function units(Request $request, ClientCondominium $condominium): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['clientes.index', 'clientes.condominios', 'clientes.unidades'],
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = ClientUnit::query()
            ->with(['condominium', 'block', 'type', 'owner', 'tenant'])
            ->where('condominium_id', $condominium->id);

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('unit_number', 'like', "%{$term}%")
                    ->orWhereHas('block', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('owner', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"))
                    ->orWhereHas('tenant', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"));
            });
        }

        $items = $query
            ->orderByRaw("CASE WHEN TRIM(unit_number) REGEXP '^[0-9]+$' THEN 0 ELSE 1 END ASC")
            ->orderByRaw("CASE WHEN TRIM(unit_number) REGEXP '^[0-9]+$' THEN CAST(TRIM(unit_number) AS UNSIGNED) END ASC")
            ->orderBy('unit_number')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (ClientUnit $unit) => HubClientPresenter::unitSummary($unit))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
        ]);
    }

    public function documents(Request $request, ClientCondominium $condominium): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['clientes.index', 'clientes.condominios'],
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $documents = ClientAttachment::query()
            ->where('related_type', 'condominium')
            ->where('related_id', $condominium->id)
            ->latest('created_at')
            ->get();

        $items = $documents->map(fn (ClientAttachment $attachment) => HubClientPresenter::document($attachment))->values()->all();

        return response()->json([
            'items' => $items,
            'groups' => HubClientPresenter::documentGroups($items),
        ]);
    }
}
