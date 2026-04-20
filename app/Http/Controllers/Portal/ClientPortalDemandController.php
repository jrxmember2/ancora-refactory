<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandCategory;
use App\Models\DemandMessage;
use App\Support\ClientPortalAccess;
use App\Support\ClientPortalAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

        $query = $access->scopeDemands(Demand::query(), $user)->with(['category']);
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

        return view('portal.demands.create', [
            'title' => 'Nova solicitação',
            'categories' => DemandCategory::query()->active()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $user->can_open_demands, 403);

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:demand_categories,id'],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:12000'],
            'files.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $demand = DB::transaction(function () use ($validated, $request, $user) {
            $demand = Demand::query()->create([
                'protocol' => $this->nextProtocol(),
                'origin' => 'portal',
                'client_portal_user_id' => $user->id,
                'client_entity_id' => $user->client_entity_id,
                'client_condominium_id' => $user->client_condominium_id,
                'category_id' => $validated['category_id'],
                'subject' => $validated['subject'],
                'description' => $validated['description'],
                'priority' => 'normal',
                'status' => 'aberta',
                'last_external_message_at' => now(),
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
        ]);
    }

    public function reply(Request $request, Demand $demand, ClientPortalAccess $access): RedirectResponse
    {
        $user = ClientPortalAuth::user($request);
        abort_unless($user && $access->canSeeDemand($user, $demand), 404);
        abort_unless($user->can_open_demands, 403);
        abort_if(in_array($demand->status, ['concluida', 'cancelada'], true), 422, 'Solicitação encerrada.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:12000'],
            'files.*' => ['nullable', 'file', 'max:20480'],
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

            $demand->update([
                'status' => $demand->status === 'aguardando_cliente' ? 'em_andamento' : $demand->status,
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

        $path = public_path(ltrim((string) $attachment->relative_path, '/'));
        abort_unless(is_file($path), 404);

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
