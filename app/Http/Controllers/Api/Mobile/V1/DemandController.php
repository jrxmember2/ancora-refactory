<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalUser;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandCategory;
use App\Models\DemandMessage;
use App\Models\DemandTag;
use App\Support\ClientPortalAccess;
use App\Support\Mobile\MobileApiContext;
use App\Support\Mobile\MobileApiPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DemandController extends Controller
{
    public function index(Request $request, ClientPortalAccess $access): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && ($user->can_view_demands || $user->can_open_demands), 403);

        $selectedCondominiumId = MobileApiContext::selectedCondominiumId($request);
        $requestedCondominiumId = (int) $request->integer('client_condominium_id');
        if ($request->boolean('all_condominiums')) {
            $selectedCondominiumId = null;
        } elseif ($requestedCondominiumId > 0 && in_array($requestedCondominiumId, $user->accessibleCondominiumIds(), true)) {
            $selectedCondominiumId = $requestedCondominiumId;
        }

        $query = $access->scopeDemands(Demand::query(), $user, $selectedCondominiumId)
            ->with(['category', 'tag', 'condominium']);

        if (!$user->can_view_demands) {
            $query->where('client_portal_user_id', $user->id);
        }

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('protocol', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%");
            });
        }

        $items = $query->latest('updated_at')->paginate(min(30, max(1, (int) $request->integer('per_page', 15))));

        return response()->json([
            'items' => collect($items->items())->map(fn (Demand $demand) => MobileApiPresenter::demandSummary($demand))->values()->all(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'status_labels' => Demand::statusLabels(),
        ]);
    }

    public function show(Request $request, Demand $demand, ClientPortalAccess $access): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);

        $demand->load([
            'tag',
            'category',
            'condominium',
            'publicMessages.portalUser',
            'publicMessages.user',
            'publicMessages.attachments' => fn ($query) => $query->where('is_internal', false),
            'attachments' => fn ($query) => $query->where('is_internal', false),
        ]);

        return response()->json([
            'item' => MobileApiPresenter::demandDetail($request, $demand, $this->canManageDemand($user, $demand)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && $user->can_open_demands, 403);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:demand_categories,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:12000'],
            'files.*' => ['nullable', 'file', 'max:30720'],
        ]);

        $condominiumIds = $user->accessibleCondominiumIds();
        $selectedCondominiumId = $this->resolveDemandCondominiumId($condominiumIds, $validated['client_condominium_id'] ?? null);
        if ($selectedCondominiumId === false) {
            return response()->json([
                'message' => 'Selecione o condominio relacionado a solicitacao.',
            ], 422);
        }

        $demand = DB::transaction(function () use ($validated, $request, $user, $selectedCondominiumId) {
            $tag = DemandTag::defaultForStatus('aberta') ?: DemandTag::default();
            $slaStartedAt = $tag?->sla_hours ? now() : null;

            $demand = Demand::query()->create([
                'protocol' => $this->nextProtocol(),
                'origin' => 'portal',
                'client_portal_user_id' => $user->id,
                'client_entity_id' => $user->client_entity_id,
                'client_condominium_id' => $selectedCondominiumId ?: null,
                'category_id' => $validated['category_id'],
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'priority' => 'normal',
                'status' => $tag?->status_key ?: 'aberta',
                'demand_tag_id' => $tag?->id,
                'last_external_message_at' => now(),
                'sla_started_at' => $slaStartedAt,
                'sla_due_at' => $slaStartedAt ? $slaStartedAt->copy()->addHours((int) $tag->sla_hours) : null,
            ]);

            $message = DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'client',
                'client_portal_user_id' => $user->id,
                'message' => $validated['description'],
                'is_internal' => false,
            ]);

            $this->storeAttachments($request, $demand, $message, 'client', $user->id, null, false);

            return $demand;
        });

        $demand->load([
            'tag',
            'category',
            'condominium',
            'publicMessages.portalUser',
            'publicMessages.user',
            'publicMessages.attachments' => fn ($query) => $query->where('is_internal', false),
            'attachments' => fn ($query) => $query->where('is_internal', false),
        ]);

        return response()->json([
            'ok' => true,
            'item' => MobileApiPresenter::demandDetail($request, $demand, $this->canManageDemand($user, $demand)),
        ], 201);
    }

    public function reply(Request $request, Demand $demand, ClientPortalAccess $access): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);
        abort_unless($user->can_open_demands, 403);
        abort_if(in_array($demand->status, ['concluida', 'cancelada'], true), 422, 'Solicitacao encerrada.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:12000'],
            'files.*' => ['nullable', 'file', 'max:30720'],
        ]);

        DB::transaction(function () use ($request, $demand, $user, $validated) {
            $message = DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'client',
                'client_portal_user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => false,
            ]);

            $this->storeAttachments($request, $demand, $message, 'client', $user->id, null, false);

            $nextStatus = $demand->status === 'aguardando_cliente' ? 'em_andamento' : $demand->status;
            $nextTag = $nextStatus !== $demand->status ? DemandTag::defaultForStatus($nextStatus) : null;
            $slaStartedAt = $nextTag?->sla_hours ? now() : null;

            $demand->update([
                'status' => $nextTag?->status_key ?: $nextStatus,
                'demand_tag_id' => $nextTag?->id ?: $demand->demand_tag_id,
                'sla_started_at' => $slaStartedAt ?: $demand->sla_started_at,
                'sla_due_at' => $slaStartedAt ? $slaStartedAt->copy()->addHours((int) $nextTag->sla_hours) : $demand->sla_due_at,
                'last_external_message_at' => now(),
            ]);
        });

        return response()->json([
            'ok' => true,
        ]);
    }

    public function cancel(Request $request, Demand $demand, ClientPortalAccess $access): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);
        abort_unless($this->canManageDemand($user, $demand), 403);

        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($demand, $user, $validated) {
            $reason = trim((string) ($validated['cancel_reason'] ?? ''));
            $message = "Solicitacao cancelada por {$user->name} em " . now()->format('d/m/Y H:i') . '.';
            if ($reason !== '') {
                $message .= "\nMotivo: {$reason}";
            }

            DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'client',
                'client_portal_user_id' => $user->id,
                'message' => $message,
                'is_internal' => false,
            ]);

            $cancelTag = DemandTag::defaultForStatus('cancelada');
            $demand->update([
                'status' => $cancelTag?->status_key ?: 'cancelada',
                'demand_tag_id' => $cancelTag?->id ?: $demand->demand_tag_id,
                'closed_at' => now(),
                'sla_started_at' => null,
                'sla_due_at' => null,
                'last_external_message_at' => now(),
            ]);
        });

        return response()->json([
            'ok' => true,
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'items' => DemandCategory::query()
                ->active()
                ->get()
                ->map(fn (DemandCategory $category) => [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                    'color' => $category->color_hex ? (string) $category->color_hex : null,
                ])->values()->all(),
        ]);
    }

    public function downloadAttachment(Request $request, Demand $demand, DemandAttachment $attachment, ClientPortalAccess $access): BinaryFileResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);
        abort_if($attachment->demand_id !== $demand->id || $attachment->is_internal, 404);

        $path = $attachment->resolvedAbsolutePath();
        abort_unless(is_string($path) && is_file($path), 404);

        return response()->download($path, $attachment->original_name);
    }

    private function nextProtocol(): string
    {
        $year = now()->year;
        $seq = (int) Demand::query()->whereYear('created_at', $year)->lockForUpdate()->count() + 1;

        return sprintf('DEM-%d-%05d', $year, $seq);
    }

    private function storeAttachments(Request $request, Demand $demand, ?DemandMessage $message, string $uploadedByType, ?int $portalUserId, ?int $userId, bool $internal): int
    {
        $files = $this->normalizeUploadedFiles($request->file('files'));
        if ($files === []) {
            return 0;
        }

        $relativeDir = 'private/client-portal-demands/' . $demand->id;
        $dir = storage_path('app/' . $relativeDir);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return 0;
        }

        $uploaded = 0;
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $stored = $this->storedFilename($file);
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
                'relative_path' => 'private://' . $relativeDir . '/' . $stored,
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

    private function storedFilename(UploadedFile $file): string
    {
        $extension = trim((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: $file->guessExtension() ?: 'bin'));
        $extension = Str::lower($extension);

        return now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension;
    }

    private function canManageDemand(ClientPortalUser $user, Demand $demand): bool
    {
        return $user->can_open_demands
            && (int) $demand->client_portal_user_id === (int) $user->id
            && !in_array($demand->status, ['concluida', 'cancelada'], true);
    }

    private function resolveDemandCondominiumId(array $condominiumIds, mixed $input): int|false
    {
        $selectedCondominiumId = (int) ($input ?? 0);

        if (count($condominiumIds) === 1) {
            return $condominiumIds[0];
        }

        if (count($condominiumIds) > 1 && !$selectedCondominiumId) {
            return false;
        }

        if ($selectedCondominiumId && !in_array($selectedCondominiumId, $condominiumIds, true)) {
            abort(403);
        }

        return $selectedCondominiumId;
    }
}
