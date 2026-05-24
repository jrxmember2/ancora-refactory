<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\ClientAttachment;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientTimeline;
use App\Models\ClientUnit;
use App\Models\User;
use App\Support\Hub\HubClientPresenter;
use App\Support\Hub\HubModulePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: $this->clientIndexRouteNames(),
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = ClientEntity::query()
            ->withCount(['ownedUnits', 'rentedUnits']);

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('display_name', 'like', "%{$term}%")
                    ->orWhere('legal_name', 'like', "%{$term}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$term}%")
                    ->orWhere('emails_json', 'like', "%{$term}%")
                    ->orWhere('phones_json', 'like', "%{$term}%");
            });
        }

        $scope = trim((string) $request->query('scope', ''));
        if ($scope === 'avulso') {
            $query->where('profile_scope', 'avulso');
        } elseif ($scope === 'condominium') {
            $query->where('profile_scope', '<>', 'avulso');
        }

        $status = trim((string) $request->query('status', ''));
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $items = $query
            ->orderByDesc('is_active')
            ->orderBy('display_name')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (ClientEntity $entity) => HubClientPresenter::clientSummary($entity))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'scopes' => HubClientPresenter::scopeOptions(),
                'statuses' => HubClientPresenter::statusOptions(),
            ],
        ]);
    }

    public function show(Request $request, ClientEntity $client): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: $this->clientIndexRouteNames(),
            moduleSlugs: ['clientes'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!$this->canAccessEntity($user, $client)) {
            return $this->notFoundResponse('Cliente não encontrado.');
        }

        $client->loadCount(['ownedUnits', 'rentedUnits'])
            ->load([
                'ownedUnits.condominium',
                'ownedUnits.block',
                'ownedUnits.type',
                'rentedUnits.condominium',
                'rentedUnits.block',
                'rentedUnits.type',
            ]);

        $documents = ClientAttachment::query()
            ->where('related_type', 'entity')
            ->where('related_id', $client->id)
            ->latest('created_at')
            ->limit(30)
            ->get();

        $timeline = ClientTimeline::query()
            ->where('related_type', 'entity')
            ->where('related_id', $client->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        $linkedCondominiums = ClientCondominium::query()
            ->with(['type', 'syndic', 'administradora'])
            ->withCount('units')
            ->where(function (Builder $query) use ($client) {
                $query->where('syndico_entity_id', $client->id)
                    ->orWhere('administradora_entity_id', $client->id);
            })
            ->orderBy('name')
            ->limit(12)
            ->get();

        $linkedUnits = collect()
            ->merge($client->ownedUnits->map(fn (ClientUnit $unit) => HubClientPresenter::unitSummary($unit, 'Proprietário')))
            ->merge($client->rentedUnits->map(fn (ClientUnit $unit) => HubClientPresenter::unitSummary($unit, 'Locatário')))
            ->unique('id')
            ->values()
            ->all();

        return response()->json([
            'item' => HubClientPresenter::clientDetail(
                entity: $client,
                documents: $documents->map(fn (ClientAttachment $attachment) => HubClientPresenter::document($attachment))->values()->all(),
                timeline: $timeline->map(fn (ClientTimeline $item) => HubClientPresenter::timelineItem($item))->values()->all(),
                linkedUnits: $linkedUnits,
                linkedCondominiums: $linkedCondominiums->map(fn (ClientCondominium $condominium) => HubClientPresenter::condominiumSummary($condominium))->values()->all(),
            ),
        ]);
    }

    private function clientIndexRouteNames(): array
    {
        return [
            'clientes.index',
            'clientes.avulsos',
            'clientes.contatos',
            'clientes.condominos',
        ];
    }

    private function canAccessEntity(User $user, ClientEntity $entity): bool
    {
        return $this->userCanAnyRoute($user, $this->entityRouteNames($entity));
    }

    private function entityRouteNames(ClientEntity $entity): array
    {
        if ($entity->profile_scope === 'avulso') {
            return ['clientes.index', 'clientes.avulsos'];
        }

        return ['clientes.index', 'clientes.contatos', 'clientes.condominos'];
    }
}
