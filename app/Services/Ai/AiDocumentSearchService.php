<?php

namespace App\Services\Ai;

use App\Models\AiDocumentChunk;
use App\Support\AiDocumentCatalog;
use Illuminate\Support\Collection;

class AiDocumentSearchService
{
    /**
     * @return array{question:string,terms:list<string>,condominium_chunks:Collection<int,AiDocumentChunk>,global_chunks:Collection<int,AiDocumentChunk>,documents:Collection<int,array<string,mixed>>}
     */
    public function search(string $question, ?int $clientCondominiumId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 30));
        $terms = $this->extractTerms($question);

        $condominiumCandidates = $clientCondominiumId
            ? $this->queryCondominiumChunks((int) $clientCondominiumId, $terms, max($limit * 4, 24))->get()
            : collect();

        $globalCandidates = $this->queryGlobalChunks($terms, max($limit * 3, 24))->get();

        $selected = $this->scoreChunks($condominiumCandidates, $question, $terms)
            ->concat($this->scoreChunks($globalCandidates, $question, $terms))
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        $selectedChunks = $selected->pluck('chunk')->values();

        return [
            'question' => $question,
            'terms' => $terms,
            'condominium_chunks' => $selectedChunks
                ->filter(fn (AiDocumentChunk $chunk) => $chunk->effectiveSourceType() === AiDocumentCatalog::SOURCE_CONDOMINIUM_ATTACHMENT)
                ->values(),
            'global_chunks' => $selectedChunks
                ->filter(fn (AiDocumentChunk $chunk) => $chunk->effectiveSourceType() === AiDocumentCatalog::SOURCE_GLOBAL_DOCUMENT)
                ->values(),
            'documents' => $this->buildDocumentMetadata($selectedChunks),
        ];
    }

    private function queryCondominiumChunks(int $clientCondominiumId, array $terms, int $limit)
    {
        return AiDocumentChunk::query()
            ->with(['clientAttachment'])
            ->where('is_active', true)
            ->whereNotNull('client_attachment_id')
            ->where(function ($query) {
                $query
                    ->where('source_type', AiDocumentCatalog::SOURCE_CONDOMINIUM_ATTACHMENT)
                    ->orWhere('origin', 'condominium');
            })
            ->where(function ($query) use ($clientCondominiumId) {
                $query
                    ->where('client_condominium_id', $clientCondominiumId)
                    ->orWhere('condominium_id', $clientCondominiumId);
            })
            ->when($terms !== [], function ($query) use ($terms) {
                $query->where(function ($search) use ($terms) {
                    foreach ($terms as $term) {
                        $search
                            ->orWhere('searchable_content', 'like', '%' . $term . '%')
                            ->orWhere('content', 'like', '%' . $term . '%')
                            ->orWhere('chunk_text', 'like', '%' . $term . '%');
                    }
                });
            })
            ->orderBy('chunk_index')
            ->orderBy('chunk_order')
            ->limit($limit);
    }

    private function queryGlobalChunks(array $terms, int $limit)
    {
        return AiDocumentChunk::query()
            ->with(['globalDocument'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->where('source_type', AiDocumentCatalog::SOURCE_GLOBAL_DOCUMENT)
                    ->orWhere('origin', 'global');
            })
            ->where(function ($query) {
                $query
                    ->whereNull('ai_global_document_id')
                    ->orWhereHas('globalDocument', function ($documentQuery) {
                        $documentQuery
                            ->where('is_active', true)
                            ->where('processing_status', 'processed');
                    });
            })
            ->when($terms !== [], function ($query) use ($terms) {
                $query->where(function ($search) use ($terms) {
                    foreach ($terms as $term) {
                        $search
                            ->orWhere('searchable_content', 'like', '%' . $term . '%')
                            ->orWhere('content', 'like', '%' . $term . '%')
                            ->orWhere('chunk_text', 'like', '%' . $term . '%');
                    }
                });
            })
            ->orderBy('chunk_index')
            ->orderBy('chunk_order')
            ->limit($limit);
    }

    /**
     * @param Collection<int,AiDocumentChunk> $chunks
     * @return Collection<int,array{chunk:AiDocumentChunk,score:int}>
     */
    private function scoreChunks(Collection $chunks, string $question, array $terms): Collection
    {
        $normalizedQuestion = AiDocumentCatalog::searchableText($question);

        return $chunks
            ->map(function (AiDocumentChunk $chunk) use ($normalizedQuestion, $terms): array {
                $searchable = $chunk->effectiveSearchableContent();
                $score = AiDocumentCatalog::documentPriority($chunk->effectiveSourceType(), $chunk->effectiveDocumentKind());

                foreach ($terms as $term) {
                    $matches = substr_count($searchable, $term);
                    if ($matches > 0) {
                        $score += 30 + min($matches, 5) * 12;
                    }
                }

                if ($normalizedQuestion !== '' && str_contains($searchable, $normalizedQuestion)) {
                    $score += 70;
                }

                return [
                    'chunk' => $chunk,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $item) => $item['score'] > 0)
            ->values();
    }

    /**
     * @param Collection<int,AiDocumentChunk> $chunks
     * @return Collection<int,array<string,mixed>>
     */
    private function buildDocumentMetadata(Collection $chunks): Collection
    {
        return $chunks
            ->map(function (AiDocumentChunk $chunk): array {
                $attachment = $chunk->clientAttachment;
                $globalDocument = $chunk->globalDocument;

                return [
                    'source_type' => $chunk->effectiveSourceType(),
                    'document_kind' => $chunk->effectiveDocumentKind(),
                    'document_kind_label' => AiDocumentCatalog::documentKindLabel($chunk->effectiveDocumentKind()),
                    'document_date' => $chunk->document_date?->toDateString()
                        ?? $attachment?->document_date?->toDateString()
                        ?? $globalDocument?->document_date?->toDateString(),
                    'client_attachment_id' => $chunk->client_attachment_id,
                    'ai_global_document_id' => $chunk->ai_global_document_id,
                    'client_condominium_id' => $chunk->client_condominium_id ?: $chunk->condominium_id,
                    'title' => $attachment?->original_name ?: $globalDocument?->name ?: $chunk->effectiveTitle(),
                ];
            })
            ->unique(fn (array $item) => implode('|', [
                $item['source_type'],
                (string) ($item['client_attachment_id'] ?? 0),
                (string) ($item['ai_global_document_id'] ?? 0),
                (string) ($item['client_condominium_id'] ?? 0),
            ]))
            ->values();
    }

    /** @return list<string> */
    private function extractTerms(string $question): array
    {
        $normalized = AiDocumentCatalog::searchableText($question);
        if ($normalized === '') {
            return [];
        }

        $stopWords = [
            'a', 'ao', 'aos', 'as', 'com', 'como', 'da', 'das', 'de', 'do', 'dos',
            'e', 'em', 'na', 'nas', 'no', 'nos', 'o', 'os', 'ou', 'para', 'por',
            'qual', 'quais', 'que', 'se', 'sem', 'sobre', 'uma', 'um',
        ];

        return collect(explode(' ', $normalized))
            ->map(fn (string $term) => trim($term))
            ->filter(fn (string $term) => $term !== '' && strlen($term) >= 3)
            ->reject(fn (string $term) => in_array($term, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }
}
