<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalUser;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandCategory;
use App\Models\DemandMessage;
use App\Models\DemandTag;
use App\Support\ClientPortalAccess;
use App\Support\ClientPortalAuth;
use App\Support\ClientPortalContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClientPortalDemandController extends Controller
{
    public function index(Request $request, ClientPortalAccess $access): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && ($user->can_view_demands || $user->can_open_demands), 403);

        $selectedCondominiumId = ClientPortalContext::selectedCondominiumId($request, $user);
        $accessibleCondominiumIds = $user->accessibleCondominiumIds();
        $requestedCondominiumId = (int) $request->query('client_condominium_id', 0);

        if ($requestedCondominiumId > 0 && in_array($requestedCondominiumId, $accessibleCondominiumIds, true)) {
            $selectedCondominiumId = $requestedCondominiumId;
        }

        $query = $access->scopeDemands(Demand::query(), $user, $selectedCondominiumId)->with(['category', 'tag']);
        if (!$user->can_view_demands) {
            $query->where('client_portal_user_id', $user->id);
        }

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($term = trim((string) $request->input('q', ''))) {
            $query->where(function ($inner) use ($term) {
                $inner->where('protocol', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%");
            });
        }

        return view('portal.demands.index', [
            'title' => 'Solicitações',
            'items' => $query->latest('updated_at')->paginate(12)->withQueryString(),
            'filters' => $request->all(),
            'statusLabels' => Demand::statusLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $user->can_open_demands, 403);
        $selectedCondominiumId = ClientPortalContext::selectedCondominiumId($request, $user);

        return view('portal.demands.create', [
            'title' => 'Nova solicitação',
            'categories' => DemandCategory::query()->active()->get(),
            'condominiums' => $user->accessibleCondominiums(),
            'selectedCondominiumId' => $selectedCondominiumId,
            'prefill' => [
                'subject' => Str::limit(trim((string) $request->query('subject', '')), 180, ''),
                'description' => Str::limit(trim((string) $request->query('description', '')), 12000, ''),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $user->can_open_demands, 403);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:demand_categories,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:12000'],
            'files.*' => ['nullable', 'file', 'max:30720'],
        ]);

        $condominiumIds = $user->accessibleCondominiumIds();
        $selectedCondominiumId = (int) ($validated['client_condominium_id'] ?? 0);
        if (count($condominiumIds) === 1) {
            $selectedCondominiumId = $condominiumIds[0];
        }

        if (count($condominiumIds) > 1 && !$selectedCondominiumId) {
            return back()->withErrors(['client_condominium_id' => 'Selecione o condominio relacionado a solicitacao.'])->withInput();
        }

        if ($selectedCondominiumId && !in_array($selectedCondominiumId, $condominiumIds, true)) {
            abort(403);
        }

        $demand = DB::transaction(function () use ($validated, $request, $user, $selectedCondominiumId) {
            $tag = DemandTag::defaultForStatus('aberta') ?: DemandTag::default();
            $slaStartedAt = $tag?->sla_hours ? now() : null;

            $demand = Demand::createWithGeneratedProtocol([
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

        return redirect()->route('portal.demands.show', $demand)->with('success', 'Solicitação aberta com sucesso.');
    }

    public function show(Request $request, Demand $demand, ClientPortalAccess $access): View
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);

        $demand->load([
            'tag',
            'category',
            'publicMessages.portalUser',
            'publicMessages.user',
            'publicMessages.attachments' => fn ($query) => $query->where('is_internal', false),
            'attachments' => fn ($query) => $query->where('is_internal', false),
        ]);

        return view('portal.demands.show', [
            'title' => $demand->protocol,
            'demand' => $demand,
            'statusLabels' => Demand::statusLabels(),
            'categories' => DemandCategory::query()->active()->get(),
            'condominiums' => $user->accessibleCondominiums(),
            'canManageDemand' => $this->canManageDemand($user, $demand),
        ]);
    }

    public function update(Request $request, Demand $demand, ClientPortalAccess $access): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);
        abort_unless($this->canManageDemand($user, $demand), 403);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:demand_categories,id'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:12000'],
        ]);

        $condominiums = $user->accessibleCondominiums();
        $selectedCondominiumId = $this->resolveDemandCondominiumId($condominiums, $validated['client_condominium_id'] ?? null);
        if ($selectedCondominiumId === false) {
            return back()->withErrors(['client_condominium_id' => 'Selecione o condominio relacionado a solicitacao.'])->withInput();
        }

        $demand->load(['category', 'condominium']);
        $newCategory = DemandCategory::query()->find($validated['category_id']);
        $newCondominium = $selectedCondominiumId ? $condominiums->firstWhere('id', (int) $selectedCondominiumId) : null;
        $changes = $this->demandChangeSummary($demand, $validated, $newCategory?->name, $newCondominium?->name, $selectedCondominiumId ?: null);

        DB::transaction(function () use ($demand, $user, $validated, $selectedCondominiumId, $changes) {
            $demand->update([
                'category_id' => $validated['category_id'],
                'client_condominium_id' => $selectedCondominiumId ?: null,
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'last_external_message_at' => now(),
            ]);

            DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'client',
                'client_portal_user_id' => $user->id,
                'message' => "Solicitacao editada por {$user->name} em " . now()->format('d/m/Y H:i') . ".\n" . ($changes !== [] ? 'Alteracoes: ' . implode('; ', $changes) . '.' : 'Edicao registrada sem alteracao de conteudo.'),
                'is_internal' => false,
            ]);
        });

        return back()->with('success', 'Solicitacao atualizada.');
    }

    public function cancel(Request $request, Demand $demand, ClientPortalAccess $access): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
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

        return redirect()->route('portal.demands.show', $demand)->with('success', 'Solicitacao cancelada.');
    }

    public function reply(Request $request, Demand $demand, ClientPortalAccess $access): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);
        abort_unless($user->can_open_demands, 403);
        abort_if(in_array($demand->status, ['concluida', 'cancelada'], true), 422, 'Solicitação encerrada.');

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

        return back()->with('success', 'Resposta enviada.');
    }

    public function downloadAttachment(Request $request, Demand $demand, DemandAttachment $attachment, ClientPortalAccess $access): BinaryFileResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);
        abort_if($attachment->demand_id !== $demand->id || $attachment->is_internal, 404);

        $path = $attachment->resolvedAbsolutePath();
        abort_unless(is_string($path) && is_file($path), 404);

        return response()->download($path, $attachment->original_name);
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

    private function resolveDemandCondominiumId(Collection $condominiums, mixed $input): int|false
    {
        $condominiumIds = $condominiums
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

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

    private function demandChangeSummary(Demand $demand, array $validated, ?string $newCategoryName, ?string $newCondominiumName, ?int $selectedCondominiumId): array
    {
        $changes = [];

        if ((int) $demand->category_id !== (int) $validated['category_id']) {
            $changes[] = 'servico de "' . ($demand->category?->name ?: 'sem servico') . '" para "' . ($newCategoryName ?: 'sem servico') . '"';
        }

        if ((int) ($demand->client_condominium_id ?? 0) !== (int) ($selectedCondominiumId ?? 0)) {
            $changes[] = 'condominio de "' . ($demand->condominium?->name ?: 'sem condominio') . '" para "' . ($newCondominiumName ?: 'sem condominio') . '"';
        }

        if ((string) $demand->subject !== (string) $validated['subject']) {
            $changes[] = 'assunto de "' . Str::limit((string) $demand->subject, 80) . '" para "' . Str::limit((string) $validated['subject'], 80) . '"';
        }

        if ((string) $demand->description !== (string) $validated['description']) {
            $changes[] = 'descricao atualizada';
        }

        return $changes;
    }
}
