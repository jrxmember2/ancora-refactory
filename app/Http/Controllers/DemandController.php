<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientPortalUser;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandCategory;
use App\Models\DemandMessage;
use App\Models\DemandTag;
use App\Models\User;
use App\Support\AncoraAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DemandController extends Controller
{
    public function dashboard(Request $request): View
    {
        $demands = Demand::query()
            ->with(['tag', 'category', 'condominium', 'entity', 'portalUser', 'assignee'])
            ->latest('updated_at')
            ->get();

        $openDemands = $demands->reject(fn (Demand $demand) => in_array($demand->status, ['concluida', 'cancelada'], true));
        $overdue = $openDemands->filter(fn (Demand $demand) => $demand->slaStatus() === 'overdue')->values();
        $atRisk = $openDemands->filter(fn (Demand $demand) => $demand->slaStatus() === 'at_risk')->values();
        $tags = DemandTag::query()->active()->get();

        $tagDistribution = $tags->map(function (DemandTag $tag) use ($demands) {
            return [
                'tag' => $tag,
                'total' => $demands->where('demand_tag_id', $tag->id)->count(),
                'open' => $demands->where('demand_tag_id', $tag->id)->reject(fn (Demand $demand) => in_array($demand->status, ['concluida', 'cancelada'], true))->count(),
            ];
        });

        return view('pages.demandas.dashboard', [
            'title' => 'Dashboard de Demandas',
            'summary' => [
                'total' => $demands->count(),
                'open' => $openDemands->count(),
                'overdue' => $overdue->count(),
                'at_risk' => $atRisk->count(),
                'waiting_client' => $openDemands->where('status', 'aguardando_cliente')->count(),
                'closed_month' => $demands
                    ->filter(fn (Demand $demand) => $demand->closed_at && $demand->closed_at->isSameMonth(now()))
                    ->count(),
            ],
            'tagDistribution' => $tagDistribution,
            'slaAttention' => $overdue->merge($atRisk)->sortBy(fn (Demand $demand) => $demand->sla_due_at?->timestamp ?? PHP_INT_MAX)->take(10),
            'latestDemands' => $demands->take(8),
        ]);
    }

    public function kanban(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'client_condominium_id' => (int) $request->integer('client_condominium_id') ?: null,
            'assigned_user_id' => (int) $request->integer('assigned_user_id') ?: null,
        ];

        $tags = DemandTag::query()->active()->get();
        $query = Demand::query()
            ->with(['tag', 'category', 'condominium', 'entity', 'portalUser', 'assignee'])
            ->latest('updated_at');

        if ($filters['client_condominium_id']) {
            $query->where('client_condominium_id', $filters['client_condominium_id']);
        }
        if ($filters['assigned_user_id']) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }
        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $query->where(function ($inner) use ($term) {
                $inner->where('protocol', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%")
                    ->orWhereHas('condominium', fn ($condo) => $condo->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('entity', fn ($entity) => $entity->where('display_name', 'like', "%{$term}%"));
            });
        }

        $demands = $query->get();
        $defaultTag = DemandTag::default();

        return view('pages.demandas.kanban', [
            'title' => 'Kanban de Demandas',
            'tags' => $tags,
            'itemsByTag' => $demands->groupBy(fn (Demand $demand) => (int) ($demand->demand_tag_id ?: $defaultTag?->id)),
            'filters' => $filters,
            'condominiums' => ClientCondominium::query()->orderBy('name')->get(),
            'users' => User::query()->active()->orderBy('name')->get(),
            'statusLabels' => Demand::statusLabels(),
        ]);
    }

    public function index(Request $request): View
    {
        $query = Demand::query()->with(['tag', 'category', 'condominium', 'entity', 'portalUser', 'assignee']);

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($priority = trim((string) $request->input('priority', ''))) {
            $query->where('priority', $priority);
        }

        if ($tagId = (int) $request->integer('demand_tag_id')) {
            $query->where('demand_tag_id', $tagId);
        }

        if ($condominiumId = (int) $request->integer('client_condominium_id')) {
            $query->where('client_condominium_id', $condominiumId);
        }

        if ($assignedUserId = (int) $request->integer('assigned_user_id')) {
            $query->where('assigned_user_id', $assignedUserId);
        }

        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function ($inner) use ($term) {
                $inner->where('protocol', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%")
                    ->orWhereHas('condominium', fn ($condo) => $condo->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('entity', fn ($entity) => $entity->where('display_name', 'like', "%{$term}%"));
            });
        }

        return view('pages.demandas.index', [
            'title' => 'Demandas',
            'items' => $query->latest('updated_at')->paginate(15)->withQueryString(),
            'filters' => $request->all(),
            'statusLabels' => Demand::statusLabels(),
            'priorityLabels' => Demand::priorityLabels(),
            'demandTags' => DemandTag::query()->active()->get(),
            'condominiums' => ClientCondominium::query()->orderBy('name')->get(),
            'users' => User::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $currentUser = AncoraAuth::user($request);

        return view('pages.demandas.create', [
            'title' => 'Nova demanda',
            'categories' => DemandCategory::query()->active()->get(),
            'demandTags' => DemandTag::query()->active()->get(),
            'priorityLabels' => Demand::priorityLabels(),
            'condominiums' => ClientCondominium::query()->orderBy('name')->get(),
            'entities' => ClientEntity::query()->active()->get(),
            'portalUsers' => ClientPortalUser::query()
                ->active()
                ->with(['entity', 'condominium', 'condominiums'])
                ->orderBy('name')
                ->get(),
            'users' => User::query()->active()->orderBy('name')->get(),
            'defaultTagId' => DemandTag::defaultForStatus('aberta')?->id ?: DemandTag::default()?->id,
            'defaultAssignedUserId' => $currentUser?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:demand_categories,id'],
            'demand_tag_id' => ['nullable', 'integer', 'exists:demand_tags,id'],
            'priority' => ['required', 'string', 'in:' . implode(',', array_keys(Demand::priorityLabels()))],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'client_entity_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'client_portal_user_id' => ['nullable', 'integer', 'exists:client_portal_users,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:12000'],
            'publish_to_portal' => ['nullable', 'boolean'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $portalUser = !empty($validated['client_portal_user_id'])
            ? ClientPortalUser::query()
                ->active()
                ->with(['entity', 'condominium', 'condominiums'])
                ->find($validated['client_portal_user_id'])
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
            ? DemandTag::query()->active()->whereKey($validated['demand_tag_id'])->first()
            : (DemandTag::defaultForStatus('aberta') ?: DemandTag::default());

        $publishToPortal = $request->boolean('publish_to_portal');

        $demand = DB::transaction(function () use (
            $request,
            $user,
            $validated,
            $portalUser,
            $selectedEntityId,
            $selectedCondominiumId,
            $tag,
            $publishToPortal
        ) {
            $state = $this->initialStatePayload($tag);

            $demand = Demand::query()->create([
                'protocol' => $this->nextProtocol(),
                'origin' => 'internal',
                'client_portal_user_id' => $portalUser?->id,
                'client_entity_id' => $selectedEntityId,
                'client_condominium_id' => $selectedCondominiumId,
                'category_id' => (int) $validated['category_id'],
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'priority' => $validated['priority'],
                'assigned_user_id' => !empty($validated['assigned_user_id']) ? (int) $validated['assigned_user_id'] : $user->id,
                'last_external_message_at' => $publishToPortal ? now() : null,
                'last_internal_message_at' => $publishToPortal ? null : now(),
                'status' => $state['status'],
                'demand_tag_id' => $state['demand_tag_id'],
                'closed_at' => $state['closed_at'],
                'sla_started_at' => $state['sla_started_at'],
                'sla_due_at' => $state['sla_due_at'],
            ]);

            $message = DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'internal',
                'user_id' => $user->id,
                'message' => $validated['description'],
                'is_internal' => !$publishToPortal,
            ]);

            $this->storeAttachments($request, $demand, $message, 'internal', null, $user->id, !$publishToPortal);

            return $demand;
        });

        return redirect()->route('demandas.show', $demand)->with('success', 'Demanda criada com sucesso.');
    }

    public function show(Demand $demanda): View
    {
        $demanda->load([
            'tag',
            'category',
            'condominium',
            'entity',
            'portalUser',
            'assignee',
            'processCase',
            'cobrancaCase',
            'messages.portalUser',
            'messages.user',
            'messages.attachments',
            'attachments',
        ]);

        return view('pages.demandas.show', [
            'title' => $demanda->protocol,
            'demand' => $demanda,
            'statusLabels' => Demand::statusLabels(),
            'priorityLabels' => Demand::priorityLabels(),
            'categories' => DemandCategory::query()->active()->get(),
            'demandTags' => DemandTag::query()->active()->get(),
            'users' => User::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Demand $demanda): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:40'],
            'priority' => ['required', 'string', 'max:30'],
            'category_id' => ['nullable', 'integer', 'exists:demand_categories,id'],
            'demand_tag_id' => ['nullable', 'integer', 'exists:demand_tags,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $updates = [
            'priority' => $validated['priority'],
            'category_id' => $validated['category_id'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
        ];

        $tag = !empty($validated['demand_tag_id'])
            ? DemandTag::query()->active()->whereKey($validated['demand_tag_id'])->first()
            : null;

        DB::transaction(function () use ($demanda, $updates, $validated, $tag, $user) {
            $demanda->update($updates);

            if ($tag) {
                $this->applyTag($demanda->fresh(['tag']), $tag, $user, true);
                return;
            }

            $demanda->update([
                'status' => $validated['status'],
                'closed_at' => in_array($validated['status'], ['concluida', 'cancelada'], true) ? ($demanda->closed_at ?: now()) : null,
            ]);
        });

        return back()->with('success', 'Demanda atualizada.');
    }

    public function updateTag(Request $request, Demand $demanda): JsonResponse|RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'demand_tag_id' => ['required', 'integer', 'exists:demand_tags,id'],
        ]);

        $tag = DemandTag::query()->active()->whereKey($validated['demand_tag_id'])->firstOrFail();

        DB::transaction(function () use ($demanda, $tag, $user) {
            $this->applyTag($demanda->fresh(['tag']), $tag, $user, true);
        });

        $demanda->refresh()->load(['tag']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Demanda movida para ' . $tag->name . '.',
                'tag' => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color_hex,
                ],
                'sla' => [
                    'status' => $demanda->slaStatus(),
                    'label' => $demanda->slaStatusLabel(),
                    'due_at' => $demanda->sla_due_at?->format('d/m/Y H:i'),
                ],
            ]);
        }

        return back()->with('success', 'Demanda movida para ' . $tag->name . '.');
    }

    public function reply(Request $request, Demand $demanda): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:12000'],
            'is_internal' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:40'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);

        DB::transaction(function () use ($request, $demanda, $user, $validated) {
            $isInternal = $request->boolean('is_internal');
            $message = DemandMessage::query()->create([
                'demand_id' => $demanda->id,
                'sender_type' => 'internal',
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => $isInternal,
            ]);

            $this->storeAttachments($request, $demanda, $message, 'internal', null, $user->id, $isInternal);

            $updates = [
                'last_internal_message_at' => now(),
            ];

            if (!$isInternal) {
                $updates['status'] = $validated['status'] ?: 'aguardando_cliente';
            } elseif (!empty($validated['status'])) {
                $updates['status'] = $validated['status'];
            }

            if (isset($updates['status']) && in_array($updates['status'], ['concluida', 'cancelada'], true)) {
                $updates['closed_at'] = $demanda->closed_at ?: now();
            }

            if (isset($updates['status'])) {
                $tag = DemandTag::defaultForStatus((string) $updates['status']);
                if ($tag) {
                    $updates = array_merge($updates, $this->tagUpdatePayload($demanda, $tag));
                }
            }

            $demanda->update($updates);
        });

        return back()->with('success', 'Resposta registrada.');
    }

    public function downloadAttachment(Demand $demanda, DemandAttachment $attachment): BinaryFileResponse
    {
        abort_if($attachment->demand_id !== $demanda->id, 404);
        $path = public_path(ltrim((string) $attachment->relative_path, '/'));
        abort_unless(is_file($path), 404);

        return response()->download($path, $attachment->original_name);
    }

    private function applyTag(Demand $demand, DemandTag $tag, ?User $user, bool $writeHistory): void
    {
        $previousTag = $demand->tag?->name ?: (Demand::statusLabels()[$demand->status] ?? $demand->status);
        if ((int) ($demand->demand_tag_id ?? 0) === (int) $tag->id) {
            return;
        }

        $payload = $this->tagUpdatePayload($demand, $tag);

        $demand->update($payload);

        if (!$writeHistory || $previousTag === $tag->name) {
            return;
        }

        $slaLine = $tag->sla_hours
            ? 'SLA recalculado para ' . $tag->sla_hours . 'h, vencendo em ' . $demand->fresh()->sla_due_at?->format('d/m/Y H:i') . '.'
            : 'Tag sem SLA ativo.';

        DemandMessage::query()->create([
            'demand_id' => $demand->id,
            'sender_type' => 'internal',
            'user_id' => $user?->id,
            'message' => 'Demanda movida de "' . $previousTag . '" para "' . $tag->name . '" por ' . ($user?->name ?: 'Sistema') . ' em ' . now()->format('d/m/Y H:i') . ".\n" . $slaLine,
            'is_internal' => true,
        ]);
    }

    private function initialStatePayload(?DemandTag $tag): array
    {
        if (!$tag) {
            return [
                'status' => 'aberta',
                'demand_tag_id' => null,
                'closed_at' => null,
                'sla_started_at' => null,
                'sla_due_at' => null,
            ];
        }

        $closedAt = ($tag->is_closing || in_array($tag->status_key, ['concluida', 'cancelada'], true))
            ? now()
            : null;
        $slaStartedAt = $tag->sla_hours && !$closedAt ? now() : null;

        return [
            'status' => $tag->status_key,
            'demand_tag_id' => $tag->id,
            'closed_at' => $closedAt,
            'sla_started_at' => $slaStartedAt,
            'sla_due_at' => $slaStartedAt ? $slaStartedAt->copy()->addHours((int) $tag->sla_hours) : null,
        ];
    }

    private function nextProtocol(): string
    {
        $year = now()->year;
        $seq = (int) Demand::query()->whereYear('created_at', $year)->lockForUpdate()->count() + 1;

        return sprintf('DEM-%d-%05d', $year, $seq);
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

    private function storeAttachments(Request $request, Demand $demand, ?DemandMessage $message, string $uploadedByType, ?int $portalUserId, ?int $userId, bool $internal): int
    {
        $files = $this->normalizeUploadedFiles($request->file('files'));
        if ($files === []) {
            return 0;
        }

        $dir = public_path('uploads/demandas/' . $demand->id);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return 0;
        }

        $uploaded = 0;
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'], true)) {
                continue;
            }

            $stored = now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
            $file->move($dir, $stored);
            $path = $dir . DIRECTORY_SEPARATOR . $stored;

            DemandAttachment::query()->create([
                'demand_id' => $demand->id,
                'message_id' => $message?->id,
                'uploaded_by_type' => $uploadedByType,
                'client_portal_user_id' => $portalUserId,
                'user_id' => $userId,
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 255, ''),
                'stored_name' => $stored,
                'relative_path' => '/uploads/demandas/' . $demand->id . '/' . $stored,
                'mime_type' => Str::limit((string) ($file->getClientMimeType() ?: $file->getMimeType() ?: ''), 120, ''),
                'file_size' => is_file($path) ? (int) (@filesize($path) ?: 0) : 0,
                'is_internal' => $internal,
            ]);
            $uploaded++;
        }

        return $uploaded;
    }

    private function normalizeUploadedFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter($files));
        }

        return [];
    }
}
