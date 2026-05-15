<?php

namespace App\Services\Ai;

use App\Models\AiOfficeChatConversation;
use App\Models\AiOfficeChatMessage;
use App\Models\ClientCondominium;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OfficeAiChatService
{
    public const SCOPE_CONDOMINIUM = 'condominium';
    public const SCOPE_LEGAL_BASE = 'legal_base';

    public const MESSAGE_NO_DOCUMENTS = 'Ainda nao ha base documental processada para consulta neste escopo.';
    public const MESSAGE_NO_RELEVANT_EXCERPT = 'Nao encontrei previsao expressa nos documentos analisados para responder com seguranca a essa pergunta.';
    public const MESSAGE_GENERIC_ERROR = 'Nao foi possivel processar sua consulta agora. Tente novamente em instantes.';

    public function __construct(
        private readonly AiDocumentSearchService $documentSearchService,
        private readonly AiService $aiService,
    ) {
    }

    /** @return Collection<int, AiOfficeChatConversation> */
    public function recentConversationsForUser(User $user, int $limit = 12): Collection
    {
        return AiOfficeChatConversation::query()
            ->with('condominium')
            ->where('user_id', (int) $user->id)
            ->where('status', '!=', 'deleted')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 30)))
            ->get();
    }

    public function hasKnowledgeBase(string $scopeType, ?ClientCondominium $condominium = null): bool
    {
        if ($scopeType === self::SCOPE_LEGAL_BASE) {
            return $this->documentSearchService->hasGlobalKnowledgeBase();
        }

        return $condominium instanceof ClientCondominium
            ? $this->documentSearchService->hasKnowledgeBase((int) $condominium->id)
            : false;
    }

    /**
     * @return array{
     *     conversation:AiOfficeChatConversation,
     *     user_message:AiOfficeChatMessage,
     *     assistant_message:AiOfficeChatMessage,
     *     ai_response:AiResponse,
     *     search:array<string,mixed>,
     *     used_relevant_chunks:bool
     * }
     */
    public function ask(
        User $user,
        string $question,
        string $scopeType,
        ?ClientCondominium $condominium = null,
        ?AiOfficeChatConversation $conversation = null,
    ): array {
        $normalizedQuestion = trim($question);
        if ($normalizedQuestion === '') {
            throw new RuntimeException('Digite uma pergunta para consultar a Leme Escritorio.');
        }

        if ($scopeType !== self::SCOPE_LEGAL_BASE && !$condominium instanceof ClientCondominium) {
            throw new RuntimeException('Selecione um condominio ou use a Base Legal Global para iniciar a conversa.');
        }

        if (!$this->hasKnowledgeBase($scopeType, $condominium)) {
            throw new RuntimeException(self::MESSAGE_NO_DOCUMENTS);
        }

        $search = $this->documentSearchService->search(
            $normalizedQuestion,
            $scopeType === self::SCOPE_CONDOMINIUM ? (int) $condominium?->id : null,
            8,
        );

        $usedRelevantChunks = (bool) ($search['has_relevant_matches'] ?? false);

        [$savedConversation, $userMessage] = $this->openConversationAndStoreUserMessage(
            user: $user,
            scopeType: $scopeType,
            condominium: $condominium,
            conversation: $conversation,
            normalizedQuestion: $normalizedQuestion,
        );

        $aiResponse = null;

        try {
            if (!$usedRelevantChunks) {
                $aiResponse = AiResponse::success(
                    provider: $this->resolvedProviderFromSettings(),
                    model: $this->resolvedModelFromSettings(),
                    text: self::MESSAGE_NO_RELEVANT_EXCERPT,
                    status: 'success',
                );
            } else {
                $aiResponse = $this->aiService->generate(
                    systemPrompt: $this->buildSystemPrompt($scopeType, $condominium),
                    userQuestion: $normalizedQuestion,
                    documentContext: $this->buildDocumentContext($search),
                    temperature: null,
                    maxTokens: null,
                );
            }

            if (!$aiResponse->ok()) {
                throw new RuntimeException($aiResponse->error ?: 'Nao foi possivel responder agora. Tente novamente em instantes.');
            }

            $assistantText = trim((string) $aiResponse->text);
            if ($assistantText === '') {
                throw new RuntimeException('A IA nao retornou uma resposta utilizavel para esta pergunta.');
            }

            if (!$usedRelevantChunks) {
                $assistantText = self::MESSAGE_NO_RELEVANT_EXCERPT;
            }

            $assistantMessage = $this->persistAssistantMessage(
                conversation: $savedConversation,
                userMessage: $userMessage,
                search: $search,
                aiResponse: $aiResponse,
                content: $assistantText,
                usedRelevantChunks: $usedRelevantChunks,
                status: $aiResponse->status,
                errorMessage: null,
            );

            return [
                'conversation' => $savedConversation->fresh(['condominium']),
                'user_message' => $userMessage->fresh(),
                'assistant_message' => $assistantMessage,
                'ai_response' => $aiResponse,
                'search' => $search,
                'used_relevant_chunks' => $usedRelevantChunks,
            ];
        } catch (\Throwable $exception) {
            $displayError = $exception instanceof RuntimeException
                ? trim($exception->getMessage())
                : self::MESSAGE_GENERIC_ERROR;

            $failureResponse = $aiResponse ?: AiResponse::failure(
                provider: $this->resolvedProviderFromSettings(),
                model: $this->resolvedModelFromSettings(),
                error: $displayError,
            );

            $this->persistAssistantMessage(
                conversation: $savedConversation,
                userMessage: $userMessage,
                search: $search,
                aiResponse: $failureResponse,
                content: $displayError !== '' ? $displayError : self::MESSAGE_GENERIC_ERROR,
                usedRelevantChunks: $usedRelevantChunks,
                status: 'error',
                errorMessage: trim($exception->getMessage()) !== '' ? trim($exception->getMessage()) : $displayError,
            );

            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException($displayError, previous: $exception);
        }
    }

    public function deleteConversationForUser(User $user, AiOfficeChatConversation $conversation): void
    {
        if ((int) $conversation->user_id !== (int) $user->id) {
            throw new RuntimeException('Voce nao possui permissao para excluir esta conversa.');
        }

        $conversation->forceFill([
            'status' => 'deleted',
            'last_message_at' => now(),
        ])->save();
    }

    /** @return array<string,string> */
    public function scopeOptions(): array
    {
        return [
            self::SCOPE_CONDOMINIUM => 'Condominio especifico',
            self::SCOPE_LEGAL_BASE => 'Somente Base Legal Global',
        ];
    }

    private function buildSystemPrompt(string $scopeType, ?ClientCondominium $condominium): string
    {
        $settings = $this->aiService->settings();
        $basePrompt = trim((string) ($settings['ai_default_system_prompt'] ?? ''));
        $legalNotice = trim((string) ($settings['ai_default_legal_notice'] ?? ''));

        $scopeLabel = $scopeType === self::SCOPE_LEGAL_BASE
            ? 'Base Legal Global'
            : ('Condominio em analise: ' . trim((string) ($condominium?->name ?? 'Nao identificado')) . '.');

        $instructions = [
            $basePrompt,
            'Voce e Leme Escritorio, a IA interna de apoio ao escritorio Ancora.',
            'Responda sempre em portugues do Brasil, com tom tecnico, claro e objetivo.',
            'Use somente os trechos documentais fornecidos pelo sistema para fundamentar a resposta.',
            'Nunca invente clausulas, artigos, atas ou previsoes que nao aparecam nos documentos analisados.',
            'Quando houver documentos especificos do condominio, priorize Convencao condominial, depois Regimento interno, depois ATAs e por fim Base Legal Global.',
            'Se a resposta vier apenas da Base Legal Global, deixe isso explicito.',
            'Se os documentos nao trouxerem previsao expressa, diga isso com clareza.',
            'Quando a pergunta envolver interpretacao sensivel ou risco juridico, recomende validacao humana do escritorio antes de concluir qualquer orientacao operacional.',
            $scopeLabel,
        ];

        if ($legalNotice !== '') {
            $instructions[] = 'Aviso juridico padrao: ' . $legalNotice;
        }

        return collect($instructions)
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->implode("\n\n");
    }

    private function buildDocumentContext(array $search): string
    {
        return collect()
            ->concat($search['condominium_chunks'] ?? collect())
            ->concat($search['global_chunks'] ?? collect())
            ->map(function ($chunk): string {
                $title = trim((string) $chunk->effectiveTitle());
                $kind = trim((string) $chunk->effectiveDocumentKind());
                $kindLabel = $kind !== '' ? $kind : 'documento';
                $header = $title !== '' ? $title : $kindLabel;
                $body = trim((string) $chunk->effectiveContent());

                return '[' . $header . "]\n" . $body;
            })
            ->filter()
            ->implode("\n\n----------------\n\n");
    }

    private function conversationTitle(string $question): string
    {
        return Str::limit($question, 90, '...');
    }

    /** @return array{0:AiOfficeChatConversation,1:AiOfficeChatMessage} */
    private function openConversationAndStoreUserMessage(
        User $user,
        string $scopeType,
        ?ClientCondominium $condominium,
        ?AiOfficeChatConversation $conversation,
        string $normalizedQuestion,
    ): array {
        return DB::transaction(function () use ($user, $scopeType, $condominium, $conversation, $normalizedQuestion): array {
            $savedConversation = $conversation ?: AiOfficeChatConversation::query()->create([
                'user_id' => (int) $user->id,
                'client_condominium_id' => $scopeType === self::SCOPE_CONDOMINIUM ? (int) $condominium?->id : null,
                'scope_type' => $scopeType,
                'title' => $this->conversationTitle($normalizedQuestion),
                'status' => 'active',
                'last_message_at' => now(),
            ]);

            $savedConversation->forceFill([
                'client_condominium_id' => $scopeType === self::SCOPE_CONDOMINIUM ? (int) $condominium?->id : null,
                'scope_type' => $scopeType,
                'title' => trim((string) $savedConversation->title) !== '' ? $savedConversation->title : $this->conversationTitle($normalizedQuestion),
                'status' => 'active',
                'last_message_at' => now(),
            ])->save();

            $userMessage = $savedConversation->messages()->create([
                'role' => 'user',
                'content' => $normalizedQuestion,
                'status' => 'success',
                'meta_json' => [
                    'question' => $normalizedQuestion,
                    'scope_type' => $scopeType,
                    'client_condominium_id' => $scopeType === self::SCOPE_CONDOMINIUM ? (int) $condominium?->id : null,
                    'client_condominium_name' => $scopeType === self::SCOPE_CONDOMINIUM ? $condominium?->name : null,
                ],
            ]);

            return [$savedConversation, $userMessage];
        });
    }

    private function persistAssistantMessage(
        AiOfficeChatConversation $conversation,
        AiOfficeChatMessage $userMessage,
        array $search,
        AiResponse $aiResponse,
        string $content,
        bool $usedRelevantChunks,
        string $status,
        ?string $errorMessage,
    ): AiOfficeChatMessage {
        $assistantMessage = DB::transaction(function () use (
            $conversation,
            $userMessage,
            $search,
            $aiResponse,
            $content,
            $usedRelevantChunks,
            $status,
            $errorMessage,
        ): AiOfficeChatMessage {
            $conversation->forceFill([
                'last_message_at' => now(),
                'last_provider' => trim((string) $aiResponse->provider) !== '' ? $aiResponse->provider : $conversation->last_provider,
                'last_model' => trim((string) $aiResponse->model) !== '' ? $aiResponse->model : $conversation->last_model,
            ])->save();

            return $conversation->messages()->create([
                'role' => 'assistant',
                'content' => trim($content) !== '' ? trim($content) : self::MESSAGE_GENERIC_ERROR,
                'status' => trim($status) !== '' ? trim($status) : $aiResponse->status,
                'provider' => $aiResponse->provider ?: null,
                'model' => $aiResponse->model ?: null,
                'source_chunks_count' => $this->selectedChunks($search)->count(),
                'token_estimate' => $aiResponse->tokenEstimate,
                'input_tokens' => $aiResponse->inputTokens,
                'output_tokens' => $aiResponse->outputTokens,
                'tokens_total' => $this->resolveTokensTotal($aiResponse),
                'error_message' => $errorMessage,
                'meta_json' => [
                    'question' => $userMessage->content,
                    'user_message_id' => (int) $userMessage->id,
                    'documents' => collect($search['documents'] ?? [])->values()->all(),
                    'terms' => $search['terms'] ?? [],
                    'used_relevant_chunks' => $usedRelevantChunks,
                ],
            ]);
        });

        return $assistantMessage->fresh(['conversation.condominium']);
    }

    private function selectedChunks(array $search): Collection
    {
        return collect()
            ->concat($search['condominium_chunks'] ?? collect())
            ->concat($search['global_chunks'] ?? collect())
            ->unique('id')
            ->values();
    }

    private function resolveTokensTotal(AiResponse $aiResponse): ?int
    {
        if ($aiResponse->inputTokens !== null || $aiResponse->outputTokens !== null) {
            return (int) (($aiResponse->inputTokens ?? 0) + ($aiResponse->outputTokens ?? 0));
        }

        return $aiResponse->tokenEstimate !== null ? (int) $aiResponse->tokenEstimate : null;
    }

    private function resolvedProviderFromSettings(): string
    {
        $settings = $this->aiService->settings();

        return strtolower(trim((string) ($settings['ai_active_provider'] ?? 'openai'))) === 'gemini'
            ? 'gemini'
            : 'openai';
    }

    private function resolvedModelFromSettings(?string $provider = null): string
    {
        $settings = $this->aiService->settings();
        $providerKey = $provider ?: $this->resolvedProviderFromSettings();

        return $providerKey === 'gemini'
            ? trim((string) ($settings['gemini_chat_model'] ?? ''))
            : trim((string) ($settings['openai_chat_model'] ?? ''));
    }
}
