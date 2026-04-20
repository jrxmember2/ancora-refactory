<?php

namespace App\Http\Controllers;

use App\Models\ClientCondominium;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandCategory;
use App\Models\DemandMessage;
use App\Models\User;
use App\Support\AncoraAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DemandController extends Controller
{
    public function index(Request $request): View
    {
        $query = Demand::query()->with(['category', 'condominium', 'entity', 'portalUser', 'assignee']);

        if ($status = trim((string) $request->input('status', ''))) {
            $query->where('status', $status);
        }

        if ($priority = trim((string) $request->input('priority', ''))) {
            $query->where('priority', $priority);
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
            'condominiums' => ClientCondominium::query()->orderBy('name')->get(),
            'users' => User::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function show(Demand $demanda): View
    {
        $demanda->load([
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
            'users' => User::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Demand $demanda): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:40'],
            'priority' => ['required', 'string', 'max:30'],
            'category_id' => ['nullable', 'integer', 'exists:demand_categories,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $demanda->update([
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'category_id' => $validated['category_id'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
            'closed_at' => in_array($validated['status'], ['concluida', 'cancelada'], true) ? ($demanda->closed_at ?: now()) : null,
        ]);

        return back()->with('success', 'Demanda atualizada.');
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
