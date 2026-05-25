<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\ClientPortalUser;
use App\Models\Demand;
use App\Models\DemandCategory;
use App\Models\DemandMessage;
use App\Models\DemandTag;
use App\Models\User;
use App\Support\Hub\HubModulePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DemandController extends HubApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['demandas.index'],
            moduleSlugs: ['demandas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $query = Demand::query()
            ->with(['category', 'tag', 'condominium', 'entity', 'portalUser', 'assignee'])
            ->withCount(['messages', 'attachments']);

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        if ($priority = trim((string) $request->query('priority', ''))) {
            $query->where('priority', $priority);
        }

        if ($assignedUserId = (int) $request->integer('assigned_user_id')) {
            $query->where('assigned_user_id', $assignedUserId);
        }

        if ($term = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('protocol', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%")
                    ->orWhereHas('condominium', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('entity', fn (Builder $query) => $query->where('display_name', 'like', "%{$term}%"))
                    ->orWhereHas('portalUser', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('assignee', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
            });
        }

        $items = $query
            ->latest('updated_at')
            ->paginate(min(50, max(10, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())
                ->map(fn (Demand $demand) => HubModulePresenter::demandSummary($demand))
                ->values()
                ->all(),
            'meta' => HubModulePresenter::pagination($items),
            'filters' => [
                'statuses' => collect(HubModulePresenter::demandStatusLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'priorities' => collect(HubModulePresenter::demandPriorityLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'categories' => $this->categoryOptions(),
                'tags' => $this->tagOptions(),
                'assignees' => $this->assigneeOptions(),
            ],
            'actions' => [
                'can_create' => $this->userCanAnyRoute($user, ['demandas.create', 'demandas.store']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['demandas.create', 'demandas.store'],
            moduleSlugs: ['demandas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $this->validateRequest($request, [
            'category_id' => ['required', 'integer', 'exists:demand_categories,id'],
            'demand_tag_id' => ['nullable', 'integer', 'exists:demand_tags,id'],
            'priority' => ['required', 'string', 'in:' . implode(',', array_keys(HubModulePresenter::demandPriorityLabels()))],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'client_entity_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'client_portal_user_id' => ['nullable', 'integer', 'exists:client_portal_users,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:12000'],
            'publish_to_portal' => ['nullable', 'boolean'],
        ], [
            'priority.in' => 'A prioridade informada é inválida.',
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $category = DemandCategory::query()
            ->active()
            ->find((int) $validated['category_id']);

        if (!$category) {
            return response()->json([
                'message' => 'A categoria informada não está disponível.',
            ], 422);
        }

        $portalUser = !empty($validated['client_portal_user_id'])
            ? ClientPortalUser::query()
                ->active()
                ->with(['entity', 'condominium', 'condominiums'])
                ->find((int) $validated['client_portal_user_id'])
            : null;

        $selectedEntityId = !empty($validated['client_entity_id']) ? (int) $validated['client_entity_id'] : null;
        $selectedCondominiumId = !empty($validated['client_condominium_id']) ? (int) $validated['client_condominium_id'] : null;

        if ($portalUser) {
            if (!$selectedEntityId && $portalUser->client_entity_id) {
                $selectedEntityId = (int) $portalUser->client_entity_id;
            }

            if (!$selectedCondominiumId) {
                $portalCondominiumIds = $portalUser->accessibleCondominiumIds();
                if (count($portalCondominiumIds) === 1) {
                    $selectedCondominiumId = (int) $portalCondominiumIds[0];
                }
            }
        }

        $tag = !empty($validated['demand_tag_id'])
            ? DemandTag::query()->active()->whereKey((int) $validated['demand_tag_id'])->first()
            : (DemandTag::defaultForStatus('aberta') ?: DemandTag::default());

        if (!empty($validated['demand_tag_id']) && !$tag) {
            return response()->json([
                'message' => 'A etapa informada não está disponível.',
            ], 422);
        }

        $state = $this->initialStatePayload($tag);
        $publishToPortal = (bool) ($validated['publish_to_portal'] ?? false);
        $assigneeId = !empty($validated['assigned_user_id']) ? (int) $validated['assigned_user_id'] : (int) $user->id;

        $demand = DB::transaction(function () use (
            $validated,
            $user,
            $selectedEntityId,
            $selectedCondominiumId,
            $portalUser,
            $publishToPortal,
            $assigneeId,
            $state
        ) {
            $demand = Demand::createWithGeneratedProtocol([
                'origin' => 'internal',
                'client_portal_user_id' => $portalUser?->id,
                'client_entity_id' => $selectedEntityId,
                'client_condominium_id' => $selectedCondominiumId,
                'category_id' => (int) $validated['category_id'],
                'subject' => trim((string) $validated['subject']),
                'description' => trim((string) $validated['description']),
                'priority' => trim((string) $validated['priority']),
                'assigned_user_id' => $assigneeId,
                'last_external_message_at' => $publishToPortal ? now() : null,
                'last_internal_message_at' => $publishToPortal ? null : now(),
                'status' => $state['status'],
                'demand_tag_id' => $state['demand_tag_id'],
                'closed_at' => $state['closed_at'],
                'sla_started_at' => $state['sla_started_at'],
                'sla_due_at' => $state['sla_due_at'],
            ]);

            DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'internal',
                'user_id' => $user->id,
                'message' => trim((string) $validated['description']),
                'is_internal' => !$publishToPortal,
            ]);

            return $demand;
        });

        return response()->json([
            'ok' => true,
            'message' => 'Demanda criada com sucesso.',
            'item' => $this->presentDemandDetail($demand->fresh(), $user),
        ], 201);
    }

    public function show(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['demandas.show', 'demandas.index'],
            moduleSlugs: ['demandas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json([
            'item' => $this->presentDemandDetail($demand, $user),
        ]);
    }

    public function reply(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['demandas.reply'],
            moduleSlugs: ['demandas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (in_array($demand->status, ['concluida', 'cancelada'], true)) {
            return response()->json([
                'message' => 'A demanda já está encerrada.',
            ], 422);
        }

        $validated = $this->validateRequest($request, [
            'message' => ['required', 'string', 'max:12000'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        DB::transaction(function () use ($demand, $user, $validated) {
            DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'internal',
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => true,
            ]);

            $updates = [
                'last_internal_message_at' => now(),
            ];

            if ($demand->status === 'aguardando_cliente') {
                $tag = DemandTag::defaultForStatus('em_andamento');
                if ($tag) {
                    $updates = array_merge($updates, $this->tagUpdatePayload($demand, $tag));
                } else {
                    $updates['status'] = 'em_andamento';
                    $updates['closed_at'] = null;
                }
            }

            $demand->update($updates);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Resposta registrada com sucesso.',
            'item' => $this->presentDemandDetail($demand->fresh(), $user),
        ]);
    }

    public function updateStatus(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['demandas.update', 'demandas.tag.update'],
            moduleSlugs: ['demandas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $this->validateRequest($request, [
            'status' => ['nullable', 'string', 'in:' . implode(',', array_keys(HubModulePresenter::demandStatusLabels()))],
            'demand_tag_id' => ['nullable', 'integer', 'exists:demand_tags,id'],
        ], [
            'status.in' => 'O status informado é inválido.',
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        if (empty($validated['status']) && empty($validated['demand_tag_id'])) {
            return response()->json([
                'message' => 'Informe o novo status da demanda.',
            ], 422);
        }

        $tag = !empty($validated['demand_tag_id'])
            ? DemandTag::query()->active()->whereKey((int) $validated['demand_tag_id'])->first()
            : (!empty($validated['status']) ? DemandTag::defaultForStatus((string) $validated['status']) : null);

        DB::transaction(function () use ($demand, $user, $validated, $tag) {
            if ($tag) {
                $this->applyTag($demand->fresh(['tag']), $tag, $user);
                return;
            }

            $status = (string) $validated['status'];
            $previousLabel = HubModulePresenter::demandStatusLabels()[$demand->status] ?? (string) $demand->status;
            $nextLabel = HubModulePresenter::demandStatusLabels()[$status] ?? $status;

            $demand->update([
                'status' => $status,
                'closed_at' => in_array($status, ['concluida', 'cancelada'], true) ? ($demand->closed_at ?: now()) : null,
                'sla_started_at' => null,
                'sla_due_at' => null,
            ]);

            DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'internal',
                'user_id' => $user->id,
                'message' => 'Status alterado de "' . $previousLabel . '" para "' . $nextLabel . '" por ' . $user->name . ' em ' . now()->format('d/m/Y H:i') . '.',
                'is_internal' => true,
            ]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Status atualizado com sucesso.',
            'item' => $this->presentDemandDetail($demand->fresh(), $user),
        ]);
    }

    public function move(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['demandas.tag.update', 'demandas.update'],
            moduleSlugs: ['demandas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $this->validateRequest($request, [
            'demand_tag_id' => ['required', 'integer', 'exists:demand_tags,id'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $tag = DemandTag::query()
            ->active()
            ->whereKey((int) $validated['demand_tag_id'])
            ->first();

        if (!$tag) {
            return response()->json([
                'message' => 'A etapa informada não está disponível.',
            ], 422);
        }

        DB::transaction(function () use ($demand, $tag, $user) {
            $this->applyTag($demand->fresh(['tag']), $tag, $user);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Demanda movida com sucesso.',
            'item' => $this->presentDemandDetail($demand->fresh(), $user),
        ]);
    }

    public function assign(Request $request, Demand $demand): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['demandas.update'],
            moduleSlugs: ['demandas'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $validated = $this->validateRequest($request, [
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $assignee = User::query()
            ->active()
            ->find((int) $validated['assigned_user_id']);

        if (!$assignee) {
            return response()->json([
                'message' => 'O responsável informado não está disponível.',
            ], 422);
        }

        DB::transaction(function () use ($demand, $user, $assignee) {
            $previousName = $demand->assignee?->name ?: 'ninguém';

            $demand->update([
                'assigned_user_id' => $assignee->id,
            ]);

            DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'internal',
                'user_id' => $user->id,
                'message' => 'Responsável atualizado de "' . $previousName . '" para "' . $assignee->name . '" por ' . $user->name . ' em ' . now()->format('d/m/Y H:i') . '.',
                'is_internal' => true,
            ]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Responsável atualizado com sucesso.',
            'item' => $this->presentDemandDetail($demand->fresh(), $user),
        ]);
    }

    private function presentDemandDetail(Demand $demand, User $viewer): array
    {
        $demand->load([
            'tag',
            'category',
            'condominium',
            'entity',
            'portalUser',
            'assignee',
            'messages.portalUser',
            'messages.user',
            'messages.attachments',
            'attachments',
        ]);

        return HubModulePresenter::demandDetail(
            $demand,
            actions: [
                'can_reply' => !in_array($demand->status, ['concluida', 'cancelada'], true)
                    && $this->userCanAnyRoute($viewer, ['demandas.reply']),
                'can_update_status' => $this->userCanAnyRoute($viewer, ['demandas.update', 'demandas.tag.update']),
                'can_move' => $this->userCanAnyRoute($viewer, ['demandas.tag.update', 'demandas.update']),
                'can_assign' => $this->userCanAnyRoute($viewer, ['demandas.update']),
            ],
            options: [
                'status_options' => collect(HubModulePresenter::demandStatusLabels())
                    ->map(fn (string $label, string $value) => HubModulePresenter::statusOption($value, $label))
                    ->values()
                    ->all(),
                'tag_options' => $this->tagOptions(),
                'assignees' => $this->assigneeOptions(),
            ],
        );
    }

    private function assigneeOptions(): array
    {
        return User::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => HubModulePresenter::assignee($user))
            ->values()
            ->all();
    }

    private function categoryOptions(): array
    {
        return DemandCategory::query()
            ->active()
            ->get()
            ->map(fn (DemandCategory $category) => HubModulePresenter::statusOption((string) $category->id, (string) $category->name))
            ->values()
            ->all();
    }

    private function tagOptions(): array
    {
        return DemandTag::query()
            ->active()
            ->get()
            ->map(fn (DemandTag $tag) => HubModulePresenter::statusOption((string) $tag->id, (string) $tag->name))
            ->values()
            ->all();
    }

    private function initialStatePayload(?DemandTag $tag): array
    {
        $status = $tag?->status_key ?: 'aberta';
        $closedAt = ($tag?->is_closing || in_array($status, ['concluida', 'cancelada'], true)) ? now() : null;
        $startedAt = ($tag?->sla_hours && !$closedAt) ? now() : null;

        return [
            'status' => $status,
            'demand_tag_id' => $tag?->id,
            'closed_at' => $closedAt,
            'sla_started_at' => $startedAt,
            'sla_due_at' => $startedAt?->copy()->addHours((int) ($tag?->sla_hours ?? 0)),
        ];
    }

    private function applyTag(Demand $demand, DemandTag $tag, User $user): void
    {
        $previousTag = $demand->tag?->name ?: (HubModulePresenter::demandStatusLabels()[$demand->status] ?? $demand->status);
        if ((int) ($demand->demand_tag_id ?? 0) === (int) $tag->id) {
            return;
        }

        $payload = $this->tagUpdatePayload($demand, $tag);
        $demand->update($payload);

        $freshDemand = $demand->fresh();
        $slaLine = $tag->sla_hours
            ? 'SLA recalculado para ' . $tag->sla_hours . 'h, vencendo em ' . ($freshDemand?->sla_due_at?->format('d/m/Y H:i') ?: 'data não informada') . '.'
            : 'Tag sem SLA ativo.';

        DemandMessage::query()->create([
            'demand_id' => $demand->id,
            'sender_type' => 'internal',
            'user_id' => $user->id,
            'message' => 'Demanda movida de "' . $previousTag . '" para "' . $tag->name . '" por ' . $user->name . ' em ' . now()->format('d/m/Y H:i') . ".\n" . $slaLine,
            'is_internal' => true,
        ]);
    }

    private function tagUpdatePayload(Demand $demand, DemandTag $tag): array
    {
        $payload = [
            'demand_tag_id' => $tag->id,
            'status' => $tag->status_key,
            'closed_at' => ($tag->is_closing || in_array($tag->status_key, ['concluida', 'cancelada'], true))
                ? ($demand->closed_at ?: now())
                : null,
        ];

        if ($tag->sla_hours && !$payload['closed_at']) {
            $startedAt = now();
            $payload['sla_started_at'] = $startedAt;
            $payload['sla_due_at'] = $startedAt->copy()->addHours((int) $tag->sla_hours);
        } else {
            $payload['sla_started_at'] = null;
            $payload['sla_due_at'] = null;
        }

        return $payload;
    }
}
