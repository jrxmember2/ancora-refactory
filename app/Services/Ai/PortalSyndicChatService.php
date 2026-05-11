<?php

namespace App\Services\Ai;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\ClientCondominium;
use App\Models\ClientPortalUser;
use App\Support\AiDocumentCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PortalSyndicChatService
{
    public const MESSAGE_NO_DOCUMENTS = 'Ainda nao ha convencao, regimento ou base documental processada para consulta. Entre em contato com o escritorio.';
    public const MESSAGE_NO_RELEVANT_EXCERPT = 'Nao encontrei previsao expressa nos documentos analisados para responder com seguranca a essa pergunta.';

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
            throw new RuntimeException('Digite uma pergunta para consultar a Leme.');
        }

        if (!$this->hasKnowledgeBase((int) $condominium->id)) {
            throw new RuntimeException(self::MESSAGE_NO_DOCUMENTS);
        }

        $search = $this->documentSearchService->search($normalizedQuestion, (int) $condominium->id, 8);
        $usedRelevantChunks = (bool) ($search['has_relevant_matches'] ?? false);
        $oldDocumentAlerts = $this->resolveOldDocumentAlerts(collect($search['documents'] ?? []));

        $aiResponse = $this->aiService->generate(
            systemPrompt: $this->buildSystemPrompt($condominium, $usedRelevantChunks, $oldDocumentAlerts),
            userQuestion: $normalizedQuestion,
            documentContext: $this->buildDocumentContext($search, $usedRelevantChunks, $oldDocumentAlerts),
            temperature: null,
            maxTokens: null,
        );

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

        $assistantText = $this->appendOldDocumentNotice($assistantText, $oldDocumentAlerts, $usedRelevantChunks);

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
            $oldDocumentAlerts,
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
                    'old_document_alerts' => $oldDocumentAlerts,
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

    private function buildSystemPrompt(ClientCondominium $condominium, bool $usedRelevantChunks, array $oldDocumentAlerts = []): string
    {
        $settings = $this->aiService->settings();
        $basePrompt = trim((string) ($settings['ai_default_system_prompt'] ?? ''));
        $legalNotice = trim((string) ($settings['ai_default_legal_notice'] ?? ''));
        $budgetUrl = trim((string) ($settings['ai_default_budget_request_url'] ?? ''));

        $instructions = [
            $basePrompt,
            'Voce e Leme, a IA que ajuda o usuario a pilotar a gestao no Portal do Cliente Ancora.',
            'Responda sempre em portugues do Brasil, com clareza, tom profissional e objetividade.',
            'Use somente os trechos documentais fornecidos pelo sistema para fundamentar a resposta.',
            'Nunca invente clausulas, artigos ou decisoes que nao aparecam nos documentos analisados.',
            'Quando houver base do condominio, priorize Convencao condominial, depois Regimento interno, depois ATAs e por fim Base Legal Global.',
            'Se os documentos nao trouxerem previsao expressa, diga isso com clareza e nao conclua alem do que esta documentado.',
            'Deixe claro quando a resposta vier apenas da Base Legal Global, sem apoio documental especifico do condominio.',
            'Condominio em analise: ' . trim((string) $condominium->name) . '.',
        ];

        if (!$usedRelevantChunks) {
            $instructions[] = 'Nenhum trecho relevante foi localizado na busca. Responda informando que nao encontrou previsao expressa nos documentos analisados.';
        }

        if ($oldDocumentAlerts !== []) {
            $instructions[] = 'Quando um documento do condominio usado na resposta estiver antigo pela regua configurada, responda a pergunta normalmente e tambem recomende, de forma objetiva, revisao ou atualizacao documental com o escritorio.';
        }

        if ($legalNotice !== '') {
            $instructions[] = 'Aviso juridico padrao: ' . $legalNotice;
        }

        if ($budgetUrl !== '') {
            $instructions[] = 'Se o usuario pedir ampliacao de plano ou orcamento, mencione este link apenas se fizer sentido: ' . $budgetUrl;
        }

        return collect($instructions)
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->implode("\n\n");
    }

    private function buildDocumentContext(array $search, bool $usedRelevantChunks, array $oldDocumentAlerts = []): string
    {
        if (!$usedRelevantChunks) {
            return 'Nenhum trecho relevante foi encontrado nos documentos processados do condominio e da Base Legal Global para esta pergunta.';
        }

        $chunksText = collect()
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

        $oldDocumentsContext = $this->buildOldDocumentContext($oldDocumentAlerts);

        if ($oldDocumentsContext === '') {
            return $chunksText;
        }

        return $oldDocumentsContext . "\n\n================\n\n" . $chunksText;
    }

    private function conversationTitle(string $question): string
    {
        return Str::limit($question, 90, '...');
    }

    /** @return array<int,array{document_kind:string,label:string,date_br:string,date_iso:string,year:string,age_years:int,threshold_years:int,title:string}> */
    private function resolveOldDocumentAlerts(Collection $documents): array
    {
        $settings = $this->aiService->settings();
        if (!(bool) ($settings['ai_old_document_alert_enabled'] ?? false)) {
            return [];
        }

        $thresholdYears = max(1, (int) ($settings['ai_old_document_alert_years'] ?? 5));
        $cutoffDate = now()->subYears($thresholdYears);

        return $documents
            ->map(function ($document) use ($cutoffDate, $thresholdYears): ?array {
                $sourceType = trim((string) data_get($document, 'source_type'));
                if ($sourceType !== AiDocumentCatalog::SOURCE_CONDOMINIUM_ATTACHMENT) {
                    return null;
                }

                $documentDate = trim((string) data_get($document, 'document_date'));
                if ($documentDate === '') {
                    return null;
                }

                try {
                    $parsedDate = Carbon::parse($documentDate)->startOfDay();
                } catch (\Throwable) {
                    return null;
                }

                if ($parsedDate->gt($cutoffDate)) {
                    return null;
                }

                $documentKind = trim((string) data_get($document, 'document_kind'));
                $label = trim((string) data_get($document, 'document_kind_label'));
                if ($label === '') {
                    $label = AiDocumentCatalog::documentKindLabel($documentKind);
                }

                return [
                    'document_kind' => $documentKind,
                    'label' => $label,
                    'date_br' => $parsedDate->format('d/m/Y'),
                    'date_iso' => $parsedDate->toDateString(),
                    'year' => $parsedDate->format('Y'),
                    'age_years' => now()->diffInYears($parsedDate),
                    'threshold_years' => $thresholdYears,
                    'title' => trim((string) data_get($document, 'title')),
                ];
            })
            ->filter()
            ->unique(fn (array $item) => implode('|', [
                $item['document_kind'],
                $item['date_iso'],
                $item['title'],
            ]))
            ->sortBy(fn (array $item) => match ($item['document_kind']) {
                'convention' => 1,
                'regiment' => 2,
                'ata' => 3,
                default => 9,
            })
            ->values()
            ->all();
    }

    private function buildOldDocumentContext(array $oldDocumentAlerts): string
    {
        if ($oldDocumentAlerts === []) {
            return '';
        }

        $thresholdYears = (int) ($oldDocumentAlerts[0]['threshold_years'] ?? 5);
        $lines = collect($oldDocumentAlerts)
            ->map(fn (array $item) => '- ' . $item['label'] . ' | data ' . $item['date_br'] . ' | idade aproximada de ' . $item['age_years'] . ' anos')
            ->implode("\n");

        return 'ALERTA DE DOCUMENTOS ANTIGOS DO CONDOMINIO (regua atual: ' . $thresholdYears . " anos)\n"
            . $lines
            . "\nUse essa informacao para orientar revisao ou atualizacao documental quando ela fizer sentido para a resposta.";
    }

    private function appendOldDocumentNotice(string $assistantText, array $oldDocumentAlerts, bool $usedRelevantChunks): string
    {
        if (!$usedRelevantChunks || $oldDocumentAlerts === []) {
            return $assistantText;
        }

        $normalizedAnswer = Str::of(Str::ascii($assistantText))->lower()->toString();
        $alreadyMentionsUpdate = Str::contains($normalizedAnswer, [
            'revisao',
            'revisar',
            'atualizacao',
            'atualizar',
            'documento antigo',
            'documentos antigos',
        ]);

        if ($alreadyMentionsUpdate) {
            return $assistantText;
        }

        $thresholdYears = (int) ($oldDocumentAlerts[0]['threshold_years'] ?? 5);

        if (count($oldDocumentAlerts) === 1) {
            $document = $oldDocumentAlerts[0];

            return rtrim($assistantText) . "\n\n"
                . 'Observacao da Leme: a ' . Str::lower($document['label']) . ' consultada e de ' . $document['year']
                . ' e ja ultrapassa a regua configurada de ' . $thresholdYears
                . ' anos para documento antigo. Vale considerar uma revisao ou atualizacao com o escritorio para manter a base documental do condominio mais segura e atualizada.';
        }

        $documentsList = collect($oldDocumentAlerts)
            ->map(fn (array $document) => $document['label'] . ' (' . $document['date_br'] . ')')
            ->implode('; ');

        return rtrim($assistantText) . "\n\n"
            . 'Observacao da Leme: os seguintes documentos consultados ja ultrapassam a regua configurada de '
            . $thresholdYears
            . ' anos para documento antigo: '
            . $documentsList
            . '. Vale considerar uma revisao ou atualizacao com o escritorio para manter a documentacao do condominio alinhada e mais segura.';
    }
}
