<?php

namespace App\Services\Ai;

use App\Models\AiDocumentChunk;
use Illuminate\Support\Collection;

class AiKnowledgeBaseService
{
    /** @return Collection<int, AiDocumentChunk> */
    public function activeGlobalChunks(int $limit = 60): Collection
    {
        return AiDocumentChunk::query()
            ->with('globalDocument')
            ->where(function ($query) {
                $query
                    ->where('source_type', 'global_document')
                    ->orWhere('origin', 'global');
            })
            ->where('is_active', true)
            ->whereHas('globalDocument', function ($query) {
                $query->where('is_active', true)->where('processing_status', 'processed');
            })
            ->orderBy('ai_global_document_id')
            ->orderBy('chunk_index')
            ->orderBy('chunk_order')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, AiDocumentChunk> */
    public function activeCondominiumChunks(int $condominiumId, int $limit = 60): Collection
    {
        return AiDocumentChunk::query()
            ->where(function ($query) {
                $query
                    ->where('source_type', 'condominium_attachment')
                    ->orWhere('origin', 'condominium');
            })
            ->where(function ($query) use ($condominiumId) {
                $query
                    ->where('client_condominium_id', $condominiumId)
                    ->orWhere('condominium_id', $condominiumId);
            })
            ->where('is_active', true)
            ->orderBy('chunk_index')
            ->orderBy('chunk_order')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, AiDocumentChunk> */
    public function syndicChatChunks(?int $condominiumId, int $limit = 80): Collection
    {
        $chunks = collect();

        if ($condominiumId && $condominiumId > 0) {
            $condominiumChunks = $this->activeCondominiumChunks($condominiumId, max((int) floor($limit * 0.5), 20));
            $chunks = $chunks->concat($condominiumChunks);
        }

        $remaining = max($limit - $chunks->count(), 20);

        return $chunks->concat($this->activeGlobalChunks($remaining))->values();
    }

    public function syndicChatContextText(?int $condominiumId, int $limit = 80): string
    {
        $chunks = $this->syndicChatChunks($condominiumId, $limit);

        return $chunks
            ->map(function (AiDocumentChunk $chunk): string {
                $prefix = trim($chunk->effectiveTitle());
                $text = trim($chunk->effectiveContent());

                return $prefix !== ''
                    ? '[' . $prefix . "]\n" . $text
                    : $text;
            })
            ->filter()
            ->implode("\n\n");
    }
}
