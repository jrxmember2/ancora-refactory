<?php

namespace App\Services\Ai\Knowledge;

use App\Services\Ai\DocumentChunker;

class AiTextChunker
{
    public function __construct(
        private readonly DocumentChunker $chunker,
    ) {
    }

    /**
     * @return list<array{chunk_order:int,chunk_text:string,reference_label:?string}>
     */
    public function chunk(string $text, int $maxCharacters = 1800): array
    {
        return collect($this->chunker->chunk($text, $maxCharacters))
            ->map(fn (array $chunk): array => [
                'chunk_order' => (int) $chunk['chunk_index'],
                'chunk_text' => $chunk['content'],
                'reference_label' => $chunk['reference_label'],
            ])
            ->values()
            ->all();
    }
}
