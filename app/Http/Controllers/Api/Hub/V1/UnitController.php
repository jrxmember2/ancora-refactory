<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\ClientAttachment;
use App\Models\ClientTimeline;
use App\Models\ClientUnit;
use App\Models\ClientUnitPartyHistory;
use App\Support\Hub\HubClientPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends HubApiController
{
    public function show(Request $request, ClientUnit $unit): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['clientes.index', 'clientes.condominios', 'clientes.unidades'],
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $unit->load([
            'condominium',
            'block',
            'type',
            'owner',
            'tenant',
        ]);

        $documents = ClientAttachment::query()
            ->where('related_type', 'unit')
            ->where('related_id', $unit->id)
            ->latest('created_at')
            ->limit(30)
            ->get();

        $timeline = ClientTimeline::query()
            ->where('related_type', 'unit')
            ->where('related_id', $unit->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        $partyHistory = ClientUnitPartyHistory::query()
            ->with(['entity', 'changedBy'])
            ->where('unit_id', $unit->id)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'item' => HubClientPresenter::unitDetail(
                unit: $unit,
                documents: $documents->map(fn (ClientAttachment $attachment) => HubClientPresenter::document($attachment))->values()->all(),
                timeline: $timeline->map(fn (ClientTimeline $item) => HubClientPresenter::timelineItem($item))->values()->all(),
                partyHistory: $partyHistory->map(fn (ClientUnitPartyHistory $item) => HubClientPresenter::unitPartyHistory($item))->values()->all(),
            ),
        ]);
    }

    public function documents(Request $request, ClientUnit $unit): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['clientes.index', 'clientes.condominios', 'clientes.unidades'],
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $documents = ClientAttachment::query()
            ->where('related_type', 'unit')
            ->where('related_id', $unit->id)
            ->latest('created_at')
            ->get();

        $items = $documents->map(fn (ClientAttachment $attachment) => HubClientPresenter::document($attachment))->values()->all();

        return response()->json([
            'items' => $items,
            'groups' => HubClientPresenter::documentGroups($items),
        ]);
    }
}
