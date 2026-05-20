<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalDeviceToken;
use App\Models\ClientPortalPushDispatch;
use App\Models\ClientPortalUser;
use App\Services\Mobile\ClientPortalPushDispatchService;
use App\Services\Mobile\FirebaseCloudMessagingService;
use App\Support\AncoraAuth;
use App\Support\Mobile\ClientPortalPushCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PushNotificationController extends Controller
{
    public function index(FirebaseCloudMessagingService $fcmService): View
    {
        return view('pages.admin.config-push', [
            'title' => 'Notificacoes Push',
            'dispatches' => ClientPortalPushDispatch::query()
                ->with('creator')
                ->orderByDesc('id')
                ->paginate(15),
            'typeOptions' => ClientPortalPushCatalog::typeOptions(),
            'statusLabels' => ClientPortalPushCatalog::statusLabels(),
            'modeLabels' => ClientPortalPushCatalog::recipientModeLabels(),
            'fcmReady' => $fcmService->enabled(),
            'fcmProjectId' => trim((string) config('services.fcm.project_id')),
            'summary' => [
                'active_portal_users' => ClientPortalUser::query()->active()->count(),
                'users_with_active_app' => ClientPortalUser::query()
                    ->active()
                    ->whereHas('deviceTokens', fn ($query) => $query->active())
                    ->count(),
                'active_device_tokens' => ClientPortalDeviceToken::query()->active()->count(),
                'last_dispatch_at' => ClientPortalPushDispatch::query()->latest('id')->value('created_at'),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        $items = ClientPortalUser::query()
            ->active()
            ->with(['entity', 'condominium', 'condominiums'])
            ->withCount([
                'deviceTokens as active_device_count' => fn ($query) => $query->active(),
            ])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    $inner
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('login_key', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhereHas('entity', function ($entityQuery) use ($term) {
                            $entityQuery->where('display_name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('condominium', function ($condominiumQuery) use ($term) {
                            $condominiumQuery->where('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('condominiums', function ($condominiumsQuery) use ($term) {
                            $condominiumsQuery->where('name', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByDesc('active_device_count')
            ->orderBy('name')
            ->limit(12)
            ->get();

        return response()->json([
            'items' => $items->map(function (ClientPortalUser $user) {
                return [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'login_key' => (string) $user->login_key,
                    'email' => $user->email ? (string) $user->email : null,
                    'client_name' => $user->displayClientName(),
                    'condominiums_label' => $user->portalCondominiumNames(),
                    'active_device_count' => (int) ($user->active_device_count ?? 0),
                ];
            })->values()->all(),
        ]);
    }

    public function send(Request $request, ClientPortalPushDispatchService $dispatchService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:4000'],
            'notification_type' => ['required', 'string', Rule::in(ClientPortalPushCatalog::typeKeys())],
            'recipient_mode' => ['required', 'string', Rule::in(['global', 'specific'])],
            'selected_user_ids' => ['nullable', 'array'],
            'selected_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('client_portal_users', 'id')->where(fn ($query) => $query->where('is_active', 1)->whereNull('deleted_at')),
            ],
        ]);

        if (($validated['recipient_mode'] ?? 'global') === 'specific' && empty($validated['selected_user_ids'])) {
            return $this->validationErrorResponse($request, [
                'selected_user_ids' => ['Selecione ao menos um usuario para o envio especifico.'],
            ]);
        }

        $admin = AncoraAuth::user($request);
        abort_unless($admin, 401);

        $payload = [
            'title' => trim((string) $validated['title']),
            'body' => trim((string) $validated['body']),
            'notification_type' => trim((string) $validated['notification_type']),
            'deep_link' => ClientPortalPushCatalog::defaultDeepLink($validated['notification_type']),
            'screen' => ClientPortalPushCatalog::defaultScreen($validated['notification_type']),
            'target_id' => null,
        ];

        $dispatch = ($validated['recipient_mode'] ?? 'global') === 'specific'
            ? $dispatchService->queueSpecificDispatch($admin, $payload, (array) ($validated['selected_user_ids'] ?? []))
            : $dispatchService->queueGlobalDispatch($admin, $payload);

        $resource = $this->dispatchResource($dispatch);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => ClientPortalPushCatalog::isFinished($dispatch->status)
                    ? 'Disparo registrado no historico. Verifique o status abaixo.'
                    : 'Disparo de push enfileirado com sucesso. O processamento continua em segundo plano.',
                'dispatch' => $resource,
            ]);
        }

        return redirect()
            ->route('config.push.index')
            ->with((string) $dispatch->status === 'failed' ? 'error' : 'success', $resource['status_message']);
    }

    public function show(ClientPortalPushDispatch $dispatch): JsonResponse
    {
        return response()->json([
            'success' => true,
            'dispatch' => $this->dispatchResource($dispatch->loadMissing('creator')),
        ]);
    }

    private function dispatchResource(ClientPortalPushDispatch $dispatch): array
    {
        $createdAt = $dispatch->created_at;
        $finishedAt = $dispatch->finished_at;
        $statusMessage = $dispatch->failure_reason
            ?: match ((string) $dispatch->status) {
                'queued' => 'Disparo aguardando processamento na fila.',
                'processing' => 'Disparo em processamento.',
                'completed' => 'Disparo concluido com sucesso.',
                'completed_with_errors' => 'Disparo concluido com pendencias em parte dos destinatarios.',
                'failed' => 'Disparo encerrado com falha.',
                default => 'Status do disparo indisponivel.',
            };

        return [
            'id' => (int) $dispatch->id,
            'title' => (string) $dispatch->title,
            'body' => (string) $dispatch->body,
            'notification_type' => (string) $dispatch->notification_type,
            'notification_type_label' => ClientPortalPushCatalog::typeLabel($dispatch->notification_type),
            'recipient_mode' => (string) $dispatch->recipient_mode,
            'recipient_mode_label' => ClientPortalPushCatalog::recipientModeLabel($dispatch->recipient_mode),
            'status' => (string) $dispatch->status,
            'status_label' => ClientPortalPushCatalog::statusLabel($dispatch->status),
            'status_message' => $statusMessage,
            'is_finished' => ClientPortalPushCatalog::isFinished($dispatch->status),
            'total_recipients' => (int) $dispatch->total_recipients,
            'success_count' => (int) $dispatch->success_count,
            'error_count' => (int) $dispatch->error_count,
            'invalid_token_count' => (int) $dispatch->invalid_token_count,
            'created_at_br' => $createdAt?->format('d/m/Y H:i'),
            'finished_at_br' => $finishedAt?->format('d/m/Y H:i'),
            'creator_name' => $dispatch->creator?->name ?: 'Sistema',
            'status_url' => route('config.push.show', $dispatch),
        ];
    }

    private function validationErrorResponse(Request $request, array $errors): JsonResponse|RedirectResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => 'Revise os dados do disparo antes de continuar.',
                'errors' => $errors,
            ], 422);
        }

        return back()
            ->withErrors($errors)
            ->withInput();
    }
}
