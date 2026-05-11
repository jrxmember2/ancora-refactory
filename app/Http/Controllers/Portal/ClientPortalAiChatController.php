<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AiChatConversation;
use App\Models\ClientCondominium;
use App\Models\ClientPortalUser;
use App\Services\Ai\AiService;
use App\Services\Ai\AiUsageLimiter;
use App\Services\Ai\PortalSyndicChatService;
use App\Support\ClientPortalAuth;
use App\Support\ClientPortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ClientPortalAiChatController extends Controller
{
    public function __construct(
        private readonly PortalSyndicChatService $chatService,
        private readonly AiUsageLimiter $usageLimiter,
    ) {
    }

    public function index(Request $request): View
    {
        $portalUser = ClientPortalAuth::user($request);
        abort_unless($portalUser, 401);

        return $this->renderChatPage($request, $portalUser, null);
    }

    public function show(Request $request, AiChatConversation $conversation): View
    {
        $portalUser = ClientPortalAuth::user($request);
        abort_unless($portalUser, 401);

        $this->assertConversationAccess($portalUser, $conversation);

        return $this->renderChatPage($request, $portalUser, $conversation->load(['messages', 'condominium']));
    }

    public function ask(Request $request): JsonResponse|RedirectResponse
    {
        $portalUser = ClientPortalAuth::user($request);
        abort_unless($portalUser, 401);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_chat_conversations,id'],
        ]);

        $conversation = null;
        if (!empty($validated['conversation_id'])) {
            $conversation = AiChatConversation::query()->with('condominium')->findOrFail((int) $validated['conversation_id']);
            $this->assertConversationAccess($portalUser, $conversation);
        }

        $chatContext = $this->resolveChatContext($request, $portalUser, $conversation);
        if ($chatContext['needs_condominium_selection']) {
            return $this->chatErrorResponse($request, 'Selecione um condominio no topo do portal para iniciar um novo chat com a Leme.', 422);
        }

        $usageStatus = $this->usageLimiter->statusForUser($portalUser, true);
        if (!$usageStatus['allowed']) {
            return $this->chatErrorResponse($request, $usageStatus['message'], 403);
        }

        $condominium = $chatContext['active_condominium'];
        if (!$condominium instanceof ClientCondominium) {
            return $this->chatErrorResponse($request, 'Nao foi possivel identificar o condominio desta conversa.', 422);
        }

        $lock = Cache::lock('portal-ai-chat:' . $portalUser->id, 30);
        if (!$lock->get()) {
            return $this->chatErrorResponse($request, 'Aguarde a resposta atual terminar antes de enviar uma nova pergunta.', 429);
        }

        try {
            $result = $this->chatService->ask(
                portalUser: $portalUser,
                condominium: $condominium,
                question: (string) $validated['question'],
                conversation: $conversation,
            );

            $updatedUsageStatus = $this->usageLimiter->incrementUsageOnSuccess($portalUser->fresh());
            $assistantMessage = $result['assistant_message'];
            $documents = collect($assistantMessage->meta_json['documents'] ?? [])->values()->all();

            $payload = [
                'ok' => true,
                'conversation_id' => $result['conversation']->id,
                'conversation_url' => route('portal.ai-chat.show', $result['conversation']),
                'active_condominium' => [
                    'id' => (int) $condominium->id,
                    'name' => $condominium->name,
                ],
                'usage_status' => $updatedUsageStatus,
                'messages' => [
                    [
                        'id' => $result['user_message']->id,
                        'role' => 'user',
                        'content' => $result['user_message']->content,
                        'created_at' => $result['user_message']->created_at?->format('d/m/Y H:i'),
                        'documents' => [],
                    ],
                    [
                        'id' => $assistantMessage->id,
                        'role' => 'assistant',
                        'content' => $assistantMessage->content,
                        'created_at' => $assistantMessage->created_at?->format('d/m/Y H:i'),
                        'documents' => $documents,
                        'provider' => $assistantMessage->provider,
                        'model' => $assistantMessage->model,
                    ],
                ],
            ];

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($payload);
            }

            return redirect()->route('portal.ai-chat.show', $result['conversation'])->with('success', 'Consulta enviada com sucesso.');
        } catch (\RuntimeException $exception) {
            return $this->chatErrorResponse($request, trim($exception->getMessage()), 422);
        } catch (\Throwable $exception) {
            return $this->chatErrorResponse($request, 'Nao foi possivel processar sua consulta agora. Tente novamente em instantes.', 500);
        } finally {
            optional($lock)->release();
        }
    }

    private function renderChatPage(Request $request, ClientPortalUser $portalUser, ?AiChatConversation $conversation): View
    {
        $usageStatus = $this->usageLimiter->statusForUser($portalUser, true);
        $chatContext = $this->resolveChatContext($request, $portalUser, $conversation);
        $activeConversation = $conversation?->loadMissing(['messages', 'condominium']);
        $activeCondominium = $chatContext['active_condominium'];
        $hasKnowledgeBase = $activeCondominium instanceof ClientCondominium
            ? $this->chatService->hasKnowledgeBase((int) $activeCondominium->id)
            : false;

        $disabledReason = null;
        if (!$usageStatus['allowed']) {
            $disabledReason = $usageStatus['message'];
        } elseif ($chatContext['needs_condominium_selection']) {
            $disabledReason = 'Selecione um condominio no topo do portal para iniciar um novo chat com a Leme.';
        } elseif (!($activeCondominium instanceof ClientCondominium)) {
            $disabledReason = 'Nao foi possivel identificar o condominio desta conversa.';
        } elseif ($activeCondominium instanceof ClientCondominium && !$hasKnowledgeBase) {
            $disabledReason = PortalSyndicChatService::MESSAGE_NO_DOCUMENTS;
        }

        return view('portal.ai-chat.index', [
            'title' => 'Leme',
            'portalUser' => $portalUser,
            'activeConversation' => $activeConversation,
            'activeMessages' => $activeConversation?->messages ?? collect(),
            'recentConversations' => $this->chatService->recentConversationsForUser($portalUser, $chatContext['recent_filter_condominium_id'], 10),
            'usageStatus' => $usageStatus,
            'activeCondominium' => $activeCondominium,
            'currentSelectedCondominium' => $chatContext['current_selected_condominium'],
            'needsCondominiumSelection' => $chatContext['needs_condominium_selection'],
            'conversationUsesDifferentCondominium' => $chatContext['conversation_uses_different_condominium'],
            'hasKnowledgeBase' => $hasKnowledgeBase,
            'chatDisabledReason' => $disabledReason,
            'chatCanSubmit' => $disabledReason === null,
            'legalNotice' => trim((string) ($this->chatServiceSettings()['ai_default_legal_notice'] ?? '')),
            'sampleQuestions' => [
                'A convencao permite locacao por temporada neste condominio?',
                'O regimento interno fala sobre barulho apos as 22h?',
                'Ha alguma ATA recente tratando de obras ou rateio extraordinario?',
                'O Codigo Civil preve deveres especificos do sindico sobre prestacao de contas?',
            ],
        ]);
    }

    private function resolveChatContext(Request $request, ClientPortalUser $portalUser, ?AiChatConversation $conversation = null): array
    {
        $accessibleCondominiums = $portalUser->accessibleCondominiums();
        $currentSelectedCondominium = ClientPortalContext::selectedCondominium($request, $portalUser);
        $currentSelectedCondominiumId = $currentSelectedCondominium?->id ? (int) $currentSelectedCondominium->id : null;
        $activeCondominium = null;
        $conversationUsesDifferentCondominium = false;

        if ($conversation && $conversation->client_condominium_id) {
            $conversationCondominiumId = (int) $conversation->client_condominium_id;
            $activeCondominium = $accessibleCondominiums->firstWhere('id', $conversationCondominiumId) ?: $conversation->condominium;
            $conversationUsesDifferentCondominium = $currentSelectedCondominiumId !== null
                && $conversationCondominiumId !== $currentSelectedCondominiumId;
        } elseif ($currentSelectedCondominium) {
            $activeCondominium = $currentSelectedCondominium;
        } elseif ($accessibleCondominiums->count() === 1) {
            $activeCondominium = $accessibleCondominiums->first();
        }

        return [
            'active_condominium' => $activeCondominium,
            'current_selected_condominium' => $currentSelectedCondominium,
            'needs_condominium_selection' => !$activeCondominium && $accessibleCondominiums->count() > 1,
            'conversation_uses_different_condominium' => $conversationUsesDifferentCondominium,
            'recent_filter_condominium_id' => $activeCondominium?->id ? (int) $activeCondominium->id : ($currentSelectedCondominiumId ?: null),
        ];
    }

    private function assertConversationAccess(ClientPortalUser $portalUser, AiChatConversation $conversation): void
    {
        abort_unless((int) $conversation->client_portal_user_id === (int) $portalUser->id, 404);

        $condominiumId = (int) ($conversation->client_condominium_id ?? 0);
        if ($condominiumId > 0) {
            abort_unless(in_array($condominiumId, $portalUser->accessibleCondominiumIds(), true), 404);
        }
    }

    private function chatErrorResponse(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        $normalized = trim($message) !== '' ? trim($message) : 'Nao foi possivel concluir sua solicitacao.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'message' => $normalized,
            ], $status);
        }

        return back()->with('error', $normalized);
    }

    private function chatServiceSettings(): array
    {
        return app(AiService::class)->settings();
    }
}
