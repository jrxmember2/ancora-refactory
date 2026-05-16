<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\AiChatConversation;
use App\Models\ClientCondominium;
use App\Models\ClientPortalUser;
use App\Services\Ai\AiUsageLimiter;
use App\Services\Ai\PortalSyndicChatService;
use App\Support\Mobile\MobileApiContext;
use App\Support\Mobile\MobileApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LemeController extends Controller
{
    public function __construct(
        private readonly PortalSyndicChatService $chatService,
        private readonly AiUsageLimiter $usageLimiter,
    ) {
    }

    public function history(Request $request): JsonResponse
    {
        $portalUser = MobileApiContext::user($request);
        abort_unless($portalUser, 401);

        $conversation = $this->resolveConversation($request, $portalUser);
        $activeCondominium = $this->resolveActiveCondominium($request, $portalUser, $conversation);

        return response()->json(array_merge(
            MobileApiPresenter::lemeConversation(
                conversation: $conversation,
                messages: $conversation?->messages ?? collect(),
                usageStatus: $this->usageLimiter->statusForUser($portalUser, true),
                activeCondominium: $activeCondominium,
            ),
            [
                'recent_conversations' => $this->chatService
                    ->recentConversationsForUser($portalUser, $activeCondominium?->id ? (int) $activeCondominium->id : null, 10)
                    ->map(fn (AiChatConversation $item) => [
                        'id' => (int) $item->id,
                        'title' => (string) $item->displayTitle(),
                        'last_message_at' => $item->last_message_at?->toAtomString(),
                    ])->values()->all(),
            ]
        ));
    }

    public function chat(Request $request): JsonResponse
    {
        $portalUser = MobileApiContext::user($request);
        abort_unless($portalUser, 401);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_chat_conversations,id'],
            'context' => ['nullable', 'array'],
        ]);

        $conversation = null;
        if (!empty($validated['conversation_id'])) {
            $conversation = AiChatConversation::query()->with(['messages', 'condominium'])->findOrFail((int) $validated['conversation_id']);
            $this->assertConversationAccess($portalUser, $conversation);
        }

        $usageStatus = $this->usageLimiter->statusForUser($portalUser, true);
        if (!$usageStatus['allowed']) {
            return response()->json([
                'message' => $usageStatus['message'],
            ], 403);
        }

        $activeCondominium = $this->resolveActiveCondominium($request, $portalUser, $conversation);
        if (!$activeCondominium instanceof ClientCondominium) {
            return response()->json([
                'message' => 'Selecione um condominio para conversar com a Leme.',
            ], 422);
        }

        $lock = Cache::lock('mobile-ai-chat:' . $portalUser->id, 30);
        if (!$lock->get()) {
            return response()->json([
                'message' => 'Aguarde a resposta atual terminar antes de enviar uma nova mensagem.',
            ], 429);
        }

        try {
            $result = $this->chatService->ask(
                portalUser: $portalUser,
                condominium: $activeCondominium,
                question: (string) $validated['message'],
                conversation: $conversation,
            );

            $updatedUsageStatus = $this->usageLimiter->incrementUsageOnSuccess($portalUser->fresh());
            $assistantMessage = $result['assistant_message'];

            return response()->json([
                'answer' => (string) $assistantMessage->content,
                'conversation_id' => (int) $result['conversation']->id,
                'created_at' => $assistantMessage->created_at?->toAtomString(),
                'usage_status' => $updatedUsageStatus,
                'messages' => [
                    MobileApiPresenter::lemeMessage($result['user_message']->fresh()),
                    MobileApiPresenter::lemeMessage($assistantMessage),
                ],
                'context' => [
                    'client_portal_user_id' => (int) $portalUser->id,
                    'client_condominium_id' => (int) $activeCondominium->id,
                    'screen' => 'mobile_app',
                ],
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => trim($exception->getMessage()) !== '' ? trim($exception->getMessage()) : 'Nao consegui responder agora. Tente novamente em alguns instantes.',
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Nao consegui responder agora. Tente novamente em alguns instantes.',
            ], 500);
        } finally {
            optional($lock)->release();
        }
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $portalUser = MobileApiContext::user($request);
        abort_unless($portalUser, 401);

        $conversation = $this->resolveConversation($request, $portalUser);
        if ($conversation) {
            $this->chatService->deleteConversationForUser($portalUser, $conversation);

            return response()->json([
                'ok' => true,
                'deleted_conversation_id' => (int) $conversation->id,
            ]);
        }

        $activeCondominium = $this->resolveActiveCondominium($request, $portalUser, null);
        $items = $this->chatService->recentConversationsForUser($portalUser, $activeCondominium?->id ? (int) $activeCondominium->id : null, 50);
        foreach ($items as $item) {
            $this->chatService->deleteConversationForUser($portalUser, $item);
        }

        return response()->json([
            'ok' => true,
            'deleted_count' => $items->count(),
        ]);
    }

    private function resolveConversation(Request $request, ClientPortalUser $portalUser): ?AiChatConversation
    {
        $conversationId = (int) $request->integer('conversation_id');
        if ($conversationId > 0) {
            $conversation = AiChatConversation::query()->with(['messages', 'condominium'])->findOrFail($conversationId);
            $this->assertConversationAccess($portalUser, $conversation);

            return $conversation;
        }

        $selectedCondominiumId = MobileApiContext::selectedCondominiumId($request);
        return $this->chatService
            ->recentConversationsForUser($portalUser, $selectedCondominiumId, 1)
            ->first();
    }

    private function resolveActiveCondominium(Request $request, ClientPortalUser $portalUser, ?AiChatConversation $conversation): ?ClientCondominium
    {
        if ($conversation && $conversation->client_condominium_id) {
            return $portalUser->accessibleCondominiums()->firstWhere('id', (int) $conversation->client_condominium_id)
                ?: $conversation->condominium;
        }

        $selected = MobileApiContext::selectedCondominium($request);
        if ($selected) {
            return $selected;
        }

        $condominiums = $portalUser->accessibleCondominiums();

        return $condominiums->count() === 1 ? $condominiums->first() : null;
    }

    private function assertConversationAccess(ClientPortalUser $portalUser, AiChatConversation $conversation): void
    {
        abort_unless((int) $conversation->client_portal_user_id === (int) $portalUser->id, 404);
        abort_unless(trim((string) $conversation->status) !== 'deleted', 404);

        $condominiumId = (int) ($conversation->client_condominium_id ?? 0);
        if ($condominiumId > 0) {
            abort_unless(in_array($condominiumId, $portalUser->accessibleCondominiumIds(), true), 404);
        }
    }
}
