<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Models\AiOfficeChatConversation;
use App\Models\ClientCondominium;
use App\Models\User;
use App\Services\Ai\AiService;
use App\Services\Ai\OfficeAiChatService;
use App\Support\Hub\HubLemePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LemeController extends HubApiController
{
    public function __construct(
        private readonly OfficeAiChatService $chatService,
        private readonly AiService $aiService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['ia.office-chat.index'],
            moduleSlugs: ['ia'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $availability = $this->availabilityPayload();
        $condominiums = $this->availableCondominiums();
        $items = $this->chatService
            ->recentConversationsForUser($user, 20)
            ->loadCount('messages')
            ->load('condominium');

        return response()->json([
            'items' => $items
                ->map(fn (AiOfficeChatConversation $conversation) => HubLemePresenter::conversationSummary($conversation))
                ->values()
                ->all(),
            'scope_options' => HubLemePresenter::scopeOptions($condominiums->isNotEmpty()),
            'condominium_options' => $condominiums
                ->map(fn (ClientCondominium $condominium) => HubLemePresenter::condominiumOption($condominium))
                ->values()
                ->all(),
            'availability' => $availability,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['ia.office-chat.index', 'ia.office-chat.ask'],
            moduleSlugs: ['ia'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if ($availabilityResponse = $this->unavailableForChatResponse()) {
            return $availabilityResponse;
        }

        $validated = $this->validateRequest($request, [
            'scope' => ['nullable', 'string', 'in:general,condominium,client,process,collection'],
            'client_condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        [$scopeType, $scopeError] = $this->resolveScopeType((string) ($validated['scope'] ?? 'general'));
        if ($scopeError) {
            return response()->json([
                'message' => $scopeError,
            ], 422);
        }

        $condominium = $this->resolveCondominiumForScope(
            scopeType: $scopeType,
            condominiumId: isset($validated['client_condominium_id']) ? (int) $validated['client_condominium_id'] : null,
        );

        if ($condominium instanceof JsonResponse) {
            return $condominium;
        }

        $conversation = AiOfficeChatConversation::query()->create([
            'user_id' => (int) $user->id,
            'client_condominium_id' => $scopeType === OfficeAiChatService::SCOPE_CONDOMINIUM ? (int) $condominium?->id : null,
            'scope_type' => $scopeType,
            'title' => null,
            'status' => 'active',
            'last_message_at' => now(),
        ])->loadCount('messages')
            ->load(['condominium', 'messages']);

        return response()->json([
            'ok' => true,
            'message' => 'Nova conversa criada com sucesso.',
            'item' => HubLemePresenter::conversationDetail($conversation),
            'availability' => $this->availabilityPayload(),
        ], 201);
    }

    public function show(Request $request, AiOfficeChatConversation $conversation): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['ia.office-chat.show', 'ia.office-chat.index'],
            moduleSlugs: ['ia'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if ($accessResponse = $this->conversationAccessResponse($user, $conversation)) {
            return $accessResponse;
        }

        $conversation->loadCount('messages')
            ->load(['condominium', 'messages']);

        return response()->json([
            'item' => HubLemePresenter::conversationDetail($conversation),
            'availability' => $this->availabilityPayload(),
        ]);
    }

    public function sendMessage(Request $request, AiOfficeChatConversation $conversation): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['ia.office-chat.ask'],
            moduleSlugs: ['ia'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if ($accessResponse = $this->conversationAccessResponse($user, $conversation)) {
            return $accessResponse;
        }

        if ($availabilityResponse = $this->unavailableForChatResponse()) {
            return $availabilityResponse;
        }

        $validated = $this->validateRequest($request, [
            'message' => ['required', 'string', 'max:4000'],
        ]);

        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $conversation->load('condominium');
        $lock = Cache::lock('hub-ai-chat:' . $user->id, 30);
        if (!$lock->get()) {
            return response()->json([
                'message' => 'Aguarde a resposta atual terminar antes de enviar uma nova mensagem.',
            ], 429);
        }

        try {
            $result = $this->chatService->ask(
                user: $user,
                question: (string) $validated['message'],
                scopeType: (string) $conversation->scope_type,
                condominium: $conversation->scope_type === OfficeAiChatService::SCOPE_CONDOMINIUM
                    ? $conversation->condominium
                    : null,
                conversation: $conversation,
            );

            $freshConversation = $result['conversation']
                ->fresh(['condominium', 'messages'])
                ->loadCount('messages');

            return response()->json([
                'ok' => true,
                'message' => 'Resposta recebida com sucesso.',
                'item' => HubLemePresenter::conversationDetail($freshConversation),
                'appended_messages' => [
                    HubLemePresenter::message($result['user_message']->fresh()),
                    HubLemePresenter::message($result['assistant_message']->fresh()),
                ],
                'availability' => $this->availabilityPayload(),
            ]);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => trim($exception->getMessage()) !== ''
                    ? trim($exception->getMessage())
                    : 'Não foi possível obter resposta agora. Tente novamente.',
            ], 422);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Não foi possível obter resposta agora. Tente novamente.',
            ], 500);
        } finally {
            optional($lock)->release();
        }
    }

    public function destroy(Request $request, AiOfficeChatConversation $conversation): JsonResponse
    {
        $user = $this->requireAuthorizedUser(
            $request,
            routeNames: ['ia.office-chat.delete'],
            moduleSlugs: ['ia'],
        );

        if ($user instanceof JsonResponse) {
            return $user;
        }

        if ($accessResponse = $this->conversationAccessResponse($user, $conversation)) {
            return $accessResponse;
        }

        $this->chatService->deleteConversationForUser($user, $conversation);

        return response()->json([
            'ok' => true,
            'message' => 'Conversa removida com sucesso.',
        ]);
    }

    private function availabilityPayload(): array
    {
        $configured = $this->tableExists('ai_office_chat_conversations')
            && $this->tableExists('ai_office_chat_messages');
        $aiEnabled = $configured && (bool) ($this->aiService->settings()['ai_enabled'] ?? false);

        $message = null;
        if (!$configured) {
            $message = 'O Leme IA ainda não foi configurado nesta instância.';
        } elseif (!$aiEnabled) {
            $message = 'O Leme IA está desativado nesta instância.';
        }

        return HubLemePresenter::availability(
            configured: $configured,
            aiEnabled: $aiEnabled,
            message: $message,
        );
    }

    private function unavailableForChatResponse(): ?JsonResponse
    {
        $availability = $this->availabilityPayload();

        if (!$availability['configured']) {
            return response()->json([
                'message' => $availability['message'] ?: 'O Leme IA ainda não foi configurado nesta instância.',
            ], 503);
        }

        if (!$availability['ai_enabled']) {
            return response()->json([
                'message' => $availability['message'] ?: 'O Leme IA está desativado nesta instância.',
            ], 403);
        }

        return null;
    }

    private function availableCondominiums()
    {
        if (!$this->tableExists('client_condominiums')) {
            return collect();
        }

        return ClientCondominium::query()
            ->orderBy('name')
            ->limit(120)
            ->get(['id', 'name']);
    }

    private function conversationAccessResponse(User $user, AiOfficeChatConversation $conversation): ?JsonResponse
    {
        if ((int) $conversation->user_id !== (int) $user->id || trim((string) $conversation->status) === 'deleted') {
            return $this->notFoundResponse('Conversa não encontrada.');
        }

        return null;
    }

    private function resolveScopeType(string $scope): array
    {
        $normalized = trim($scope) !== '' ? trim($scope) : 'general';

        return match ($normalized) {
            'general' => [OfficeAiChatService::SCOPE_LEGAL_BASE, null],
            'condominium' => [OfficeAiChatService::SCOPE_CONDOMINIUM, null],
            'client', 'process', 'collection' => [null, 'Este escopo ainda não está disponível no aplicativo.'],
            default => [null, 'O escopo informado é inválido.'],
        };
    }

    private function resolveCondominiumForScope(
        ?string $scopeType,
        ?int $condominiumId,
    ): ClientCondominium|JsonResponse|null {
        if ($scopeType !== OfficeAiChatService::SCOPE_CONDOMINIUM) {
            return null;
        }

        if (!$condominiumId) {
            return response()->json([
                'message' => 'Selecione um condomínio para iniciar a conversa neste escopo.',
            ], 422);
        }

        $condominium = ClientCondominium::query()->find($condominiumId);
        if (!$condominium) {
            return response()->json([
                'message' => 'O condomínio informado não foi encontrado.',
            ], 422);
        }

        return $condominium;
    }
}
