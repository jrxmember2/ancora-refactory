<?php

namespace App\Http\Controllers;

use App\Models\AiOfficeChatConversation;
use App\Models\ClientCondominium;
use App\Services\Ai\AiService;
use App\Services\Ai\OfficeAiChatService;
use App\Support\AncoraAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AiOfficeChatController extends Controller
{
    public function __construct(
        private readonly OfficeAiChatService $chatService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        return $this->renderChatPage($request, $user, null);
    }

    public function show(Request $request, AiOfficeChatConversation $conversation): View
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $this->assertConversationAccess($user->id, $conversation);

        return $this->renderChatPage($request, $user, $conversation->load(['messages', 'condominium']));
    }

    public function ask(Request $request): JsonResponse|RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_office_chat_conversations,id'],
            'scope_type' => ['nullable', 'string', 'in:condominium,legal_base'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
        ]);

        $conversation = null;
        if (!empty($validated['conversation_id'])) {
            $conversation = AiOfficeChatConversation::query()
                ->with('condominium')
                ->findOrFail((int) $validated['conversation_id']);

            $this->assertConversationAccess($user->id, $conversation);
        }

        $scopeType = $conversation?->scope_type ?: (string) ($validated['scope_type'] ?? OfficeAiChatService::SCOPE_CONDOMINIUM);
        $condominium = null;

        if ($scopeType === OfficeAiChatService::SCOPE_CONDOMINIUM) {
            $condominiumId = $conversation?->client_condominium_id ?: ($validated['client_condominium_id'] ?? null);
            if ($condominiumId) {
                $condominium = ClientCondominium::query()->find((int) $condominiumId);
            }
        }

        if (!$this->aiEnabled()) {
            return $this->chatErrorResponse($request, 'A Inteligencia Artificial esta desativada nas configuracoes.', 403);
        }

        if ($scopeType === OfficeAiChatService::SCOPE_CONDOMINIUM && !$condominium instanceof ClientCondominium) {
            return $this->chatErrorResponse($request, 'Selecione um condominio para iniciar a conversa ou troque o escopo para Base Legal Global.', 422);
        }

        $lock = Cache::lock('office-ai-chat:' . $user->id, 30);
        if (!$lock->get()) {
            return $this->chatErrorResponse($request, 'Aguarde a resposta atual terminar antes de enviar uma nova pergunta.', 429);
        }

        try {
            $result = $this->chatService->ask(
                user: $user,
                question: (string) $validated['question'],
                scopeType: $scopeType,
                condominium: $condominium,
                conversation: $conversation,
            );

            $assistantMessage = $result['assistant_message'];
            $documents = collect($assistantMessage->meta_json['documents'] ?? [])->values()->all();
            $conversationScopeType = (string) $result['conversation']->scope_type;

            $payload = [
                'ok' => true,
                'conversation_id' => $result['conversation']->id,
                'conversation_url' => route('ia.office-chat.show', $result['conversation']),
                'scope' => [
                    'type' => $conversationScopeType,
                    'label' => $conversationScopeType === OfficeAiChatService::SCOPE_LEGAL_BASE
                        ? 'Base Legal Global'
                        : ($result['conversation']->condominium?->name ?: 'Condominio'),
                ],
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

            return redirect()->route('ia.office-chat.show', $result['conversation'])->with('success', 'Consulta enviada com sucesso.');
        } catch (\RuntimeException $exception) {
            return $this->chatErrorResponse($request, trim($exception->getMessage()), 422);
        } catch (\Throwable $exception) {
            return $this->chatErrorResponse($request, 'Nao foi possivel processar sua consulta agora. Tente novamente em instantes.', 500);
        } finally {
            optional($lock)->release();
        }
    }

    public function destroy(Request $request, AiOfficeChatConversation $conversation): RedirectResponse
    {
        $user = AncoraAuth::user($request);
        abort_unless($user, 401);

        $this->assertConversationAccess($user->id, $conversation);
        $this->chatService->deleteConversationForUser($user, $conversation);

        return $this->safeRedirect($request, route('ia.office-chat.index'))
            ->with('success', 'Chat excluido com sucesso.');
    }

    private function renderChatPage(Request $request, $user, ?AiOfficeChatConversation $conversation): View
    {
        $activeConversation = $conversation?->loadMissing(['messages', 'condominium']);
        $settings = app(AiService::class)->settings();
        $scopeOptions = $this->chatService->scopeOptions();
        $selectedScopeType = $activeConversation?->scope_type ?: (string) old('scope_type', OfficeAiChatService::SCOPE_CONDOMINIUM);
        $selectedCondominiumId = $activeConversation?->client_condominium_id ?: (int) old('client_condominium_id', 0);
        $condominiums = ClientCondominium::query()->orderBy('name')->get(['id', 'name']);
        $selectedCondominium = $selectedCondominiumId > 0
            ? $condominiums->firstWhere('id', $selectedCondominiumId)
            : null;

        $disabledReason = null;
        if (!$this->aiEnabled()) {
            $disabledReason = 'A Inteligencia Artificial esta desativada nas configuracoes.';
        } elseif ($activeConversation) {
            if (!$this->chatService->hasKnowledgeBase((string) $activeConversation->scope_type, $activeConversation->condominium)) {
                $disabledReason = OfficeAiChatService::MESSAGE_NO_DOCUMENTS;
            }
        } elseif ($selectedScopeType === OfficeAiChatService::SCOPE_CONDOMINIUM && !$selectedCondominium instanceof ClientCondominium) {
            $disabledReason = 'Selecione um condominio para iniciar a conversa ou troque o escopo para Base Legal Global.';
        } elseif (!$this->chatService->hasKnowledgeBase($selectedScopeType, $selectedCondominium)) {
            $disabledReason = OfficeAiChatService::MESSAGE_NO_DOCUMENTS;
        }

        return view('pages.admin.ai-office-chat.index', [
            'title' => 'Leme Escritorio',
            'activeConversation' => $activeConversation,
            'activeMessages' => $activeConversation?->messages ?? collect(),
            'recentConversations' => $this->chatService->recentConversationsForUser($user, 12),
            'condominiums' => $condominiums,
            'selectedScopeType' => $selectedScopeType,
            'selectedCondominium' => $selectedCondominium,
            'scopeOptions' => $scopeOptions,
            'chatDisabledReason' => $disabledReason,
            'chatCanSubmit' => $disabledReason === null,
            'legalNotice' => trim((string) ($settings['ai_default_legal_notice'] ?? '')),
            'sampleQuestions' => [
                'Existe previsao expressa sobre locacao por temporada na convencao deste condominio?',
                'O regimento interno trata de barulho apos as 22h?',
                'Ha alguma ATA recente sobre obras, rateio extra ou uso do salao de festas?',
                'O Codigo Civil traz fundamento para prestacao de contas do sindico?',
            ],
        ]);
    }

    private function assertConversationAccess(int $userId, AiOfficeChatConversation $conversation): void
    {
        abort_unless((int) $conversation->user_id === $userId, 404);
        abort_unless(trim((string) $conversation->status) !== 'deleted', 404);
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

    private function aiEnabled(): bool
    {
        return (bool) (app(AiService::class)->settings()['ai_enabled'] ?? false);
    }

    private function safeRedirect(Request $request, string $fallback): RedirectResponse
    {
        $returnTo = trim((string) $request->input('return_to', ''));
        if ($returnTo === '') {
            return redirect()->to($fallback);
        }

        if (str_starts_with($returnTo, '/') && !str_starts_with($returnTo, '//')) {
            return redirect()->to($returnTo);
        }

        $targetHost = parse_url($returnTo, PHP_URL_HOST);
        if ($targetHost && $targetHost === $request->getHost()) {
            return redirect()->to($returnTo);
        }

        return redirect()->to($fallback);
    }
}
