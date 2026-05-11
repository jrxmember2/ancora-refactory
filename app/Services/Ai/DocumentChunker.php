<?php

namespace App\Services\Ai;

use App\Support\AiDocumentCatalog;

class DocumentChunker
{
    /**
     * @return list<array{chunk_index:int,title:?string,content:string,searchable_content:string,reference_label:?string}>
     */
    public function chunk(string $text, int $maxCharacters = 1800): array
    {
        $paragraphs = preg_split('/\n+/u', trim($text)) ?: [];
        $chunks = [];
        $buffer = [];
        $bufferLength = 0;
        $chunkIndex = 1;
        $currentReference = null;
        $bufferReference = null;
        $bufferTitle = null;

        foreach ($paragraphs as $paragraph) {
            $clean = $this->normalizeParagraph($paragraph);
            if ($clean === '') {
                continue;
            }

            $paragraphReference = $this->detectReference($clean);
            if ($paragraphReference !== null) {
                $currentReference = $paragraphReference;
            }

            $candidateLength = $bufferLength === 0
                ? mb_strlen($clean, 'UTF-8')
                : $bufferLength + 2 + mb_strlen($clean, 'UTF-8');

            if ($buffer !== [] && $candidateLength > $maxCharacters) {
                $content = implode("\n\n", $buffer);
                $chunks[] = [
                    'chunk_index' => $chunkIndex++,
                    'title' => $bufferTitle,
                    'content' => $content,
                    'searchable_content' => AiDocumentCatalog::searchableText($content),
                    'reference_label' => $bufferReference,
                ];

                $buffer = [];
                $bufferLength = 0;
                $bufferReference = null;
                $bufferTitle = null;
            }

            $buffer[] = $clean;
            $bufferLength = $bufferLength === 0
                ? mb_strlen($clean, 'UTF-8')
                : $bufferLength + 2 + mb_strlen($clean, 'UTF-8');

            $bufferReference ??= $paragraphReference ?? $currentReference ?? $this->fallbackReference($clean);
            $bufferTitle ??= $paragraphReference ?? $this->fallbackTitle($clean);
        }

        if ($buffer !== []) {
            $content = implode("\n\n", $buffer);
            $chunks[] = [
                'chunk_index' => $chunkIndex,
                'title' => $bufferTitle,
                'content' => $content,
                'searchable_content' => AiDocumentCatalog::searchableText($content),
                'reference_label' => $bufferReference,
            ];
        }

        return $chunks;
    }

    private function normalizeParagraph(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return trim($value);
    }

    private function detectReference(string $paragraph): ?string
    {
        if (preg_match('/^(Art\.?\s*\d+[A-Za-z0-9º°\-\.\,]*)(.*)$/iu', $paragraph, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^(Livro|Titulo|Capitulo|Secao|Subsecao|Parte)\s+[A-Z0-9IVXLCDM\-]+/iu', $paragraph, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }

    private function fallbackReference(string $paragraph): string
    {
        return $this->fallbackTitle($paragraph);
    }

    private function fallbackTitle(string $paragraph): string
    {
        $snippet = mb_substr($paragraph, 0, 110, 'UTF-8');

        return $snippet . (mb_strlen($paragraph, 'UTF-8') > 110 ? '...' : '');
    }
}
