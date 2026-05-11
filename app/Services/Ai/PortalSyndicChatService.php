<?php

namespace App\Services\Ai;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\ClientCondominium;
use App\Models\ClientPortalUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PortalSyndicChatService
{
    public const MESSAGE_NO_DOCUMENTS = 'Ainda não há convenção, regimento ou base documental processada para consulta. Entre em contato com o escritório.';
    public const MESSAGE_NO_RELEVANT_EXCERPT = 'Não encontrei previsão expressa nos documentos analisados para responder com segurança a essa pergunta.';

    public function __construct(
        private readonly AiDocumentSearchService $documentSearchService,
        private readonly AiService $aiService,
    ) {
    }

    /** @return Collection<int, AiChatConversation> */
    public function recentConversationsForUser(ClientPortalUser $portalUser, ?int $clientCondominiumId = null, int $limit = 10): Collection
    {
        return AiChatConversation::query()
            ->with('condominium')
            ->where('client_portal_user_id', $portalUser->id)
            ->when($clientCondominiumId, function ($query) use ($clientCondominiumId) {
                $query->where('client_condominium_id', (int) $clientCondominiumId);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 20)))
            ->get();
    }

    public function hasKnowledgeBase(?int $clientCondominiumId): bool
    {
        return $this->documentSearchService->hasKnowledgeBase($clientCondominiumId);
    }

    /**
     * @return array{
     *     conversation:AiChatConversation,
     *     user_message:AiChatMessage,
     *     assistant_message:AiChatMessage,
     *     ai_response:AiResponse,
     *     search:array<string,mixed>,
     *     used_relevant_chunks:bool
     * }
     */
    public function ask(
        ClientPortalUser $portalUser,
        ClientCondominium $condominium,
        string $question,
        ?AiChatConversation $conversation = null,
    ): array {
        $normalizedQuestion = trim($question);
        if ($normalizedQuestion === '') {
            throw new RuntimeException('Digite uma pergunta para consultar o Chat do Síndico.');
        }

        if (!$this->hasKnowledgeBase((int) $condominium->id)) {
            throw new RuntimeException(self::MESSAGE_NO_DOCUMENTS);
        }

        $search = $this->documentSearchService->search($normalizedQuestion, (int) $condominium->id, 8);
        $usedRelevantChunks = (bool) ($search['has_relevant_matches'] ?? false);

        $aiResponse = $this->aiService->generate(
            systemPrompt: $this->buildSystemPrompt($condominium, $usedRelevantChunks),
            userQuestion: $normalizedQuestion,
            documentContext: $this->buildDocumentContext($search, $usedRelevantChunks),
            temperature: null,
            maxTokens: null,
        );

        if (!$aiResponse->ok()) {
            throw new RuntimeException($aiResponse->error ?: 'Não foi possível responder agora. Tente novamente em instantes.');
        }

        $assistantText = trim((string) $aiResponse->text);
        if ($assistantText === '') {
            throw new RuntimeException('A IA não retornou uma resposta utilizável para esta pergunta.');
        }

        if (!$usedRelevantChunks) {
            $assistantText = self::MESSAGE_NO_RELEVANT_EXCERPT;
        }

        $savedConversation = null;
        $userMessage = null;
        $assistantMessage = null;

        DB::transaction(function () use (
            $portalUser,
            $condominium,
            $conversation,
            $normalizedQuestion,
            $assistantText,
            $search,
            $aiResponse,
            &$savedConversation,
            &$userMessage,
            &$assistantMessage,
        ): void {
            $savedConversation = $conversation ?: AiChatConversation::query()->create([
                'client_portal_user_id' => (int) $portalUser->id,
                'client_condominium_id' => (int) $condominium->id,
                'title' => $this->conversationTitle($normalizedQuestion),
                'status' => 'active',
                'last_message_at' => now(),
                'last_provider' => $aiResponse->provider,
                'last_model' => $aiResponse->model,
            ]);

            $savedConversation->forceFill([
                'client_condominium_id' => (int) $condominium->id,
                'title' => trim((string) $savedConversation->title) !== '' ? $savedConversation->title : $this->conversationTitle($normalizedQuestion),
                'last_message_at' => now(),
                'last_provider' => $aiResponse->provider,
                'last_model' => $aiResponse->model,
            ])->save();

            $userMessage = $savedConversation->messages()->create([
                'role' => 'user',
                'content' => $normalizedQuestion,
                'status' => 'success',
                'meta_json' => [
                    'client_condominium_id' => (int) $condominium->id,
                    'client_condominium_name' => $condominium->name,
                ],
            ]);

            $assistantMessage = $savedConversation->messages()->create([
                'role' => 'assistant',
                'content' => $assistantText,
                'status' => $aiResponse->status,
                'provider' => $aiResponse->provider,
                'model' => $aiResponse->model,
                'source_chunks_count' => count(($search['condominium_chunks'] ?? collect())->all()) + count(($search['global_chunks'] ?? collect())->all()),
                'token_estimate' => $aiResponse->tokenEstimate,
                'input_tokens' => $aiResponse->inputTokens,
                'output_tokens' => $aiResponse->outputTokens,
                'meta_json' => [
                    'documents' => collect($search['documents'] ?? [])->values()->all(),
                    'terms' => $search['terms'] ?? [],
                    'used_relevant_chunks' => (bool) ($search['has_relevant_matches'] ?? false),
                ],
            ]);
        });

        return [
            'conversation' => $savedConversation,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'ai_response' => $aiResponse,
            'search' => $search,
            'used_relevant_chunks' => $usedRelevantChunks,
        ];
    }

    private function buildSystemPrompt(ClientCondominium $condominium, bool $usedRelevantChunks): string
    {
        $settings = $this->aiService->settings();
        $basePrompt = trim((string) ($settings['ai_default_system_prompt'] ?? ''));
        $legalNotice = trim((string) ($settings['ai_default_legal_notice'] ?? ''));
        $budgetUrl = trim((string) ($settings['ai_default_budget_request_url'] ?? ''));

        $instructions = [
            $basePrompt,
            'Você é o Chat do Síndico do Portal do Cliente Âncora.',
            'Responda sempre em português do Brasil, com clareza, tom profissional e objetividade.',
            'Use somente os trechos documentais fornecidos pelo sistema para fundamentar a resposta.',
            'Nunca invente cláusulas, artigos ou decisões que não apareçam nos documentos analisados.',
            'Quando houver base do condomínio, priorize Convenção condominial, depois Regimento interno, depois ATAs e por fim Base Legal Global.',
            'Se os documentos não trouxerem previsão expressa, diga isso com clareza e não conclua além do que está documentado.',
            'Deixe claro quando a resposta vier apenas da Base Legal Global, sem apoio documental específico do condomínio.',
            'Condomínio em análise: ' . trim((string) $condominium->name) . '.',
        ];

        if (!$usedRelevantChunks) {
            $instructions[] = 'Nenhum trecho relevante foi localizado na busca. Responda informando que não encontrou previsão expressa nos documentos analisados.';
        }

        if ($legalNotice !== '') {
            $instructions[] = 'Aviso jurídico padrão: ' . $legalNotice;
        }

        if ($budgetUrl !== '') {
            $instructions[] = 'Se o usuário pedir ampliação de plano ou orçamento, mencione este link apenas se fizer sentido: ' . $budgetUrl;
        }

        return collect($instructions)
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->implode("\n\n");
    }

    private function buildDocumentContext(array $search, bool $usedRelevantChunks): string
    {
        if (!$usedRelevantChunks) {
            return 'Nenhum trecho relevante foi encontrado nos documentos processados do condomínio e da Base Legal Global para esta pergunta.';
        }

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
}
