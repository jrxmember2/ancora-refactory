<?php

namespace App\Services\Ai\Knowledge;

class AiTextChunker
{
    /**
     * @return list<array{chunk_order:int,chunk_text:string,reference_label:?string}>
     */
    public function chunk(string $text, int $maxCharacters = 1800): array
    {
        $paragraphs = preg_split('/\n+/u', trim($text)) ?: [];
        $chunks = [];
        $buffer = [];
        $bufferLength = 0;
        $order = 1;
        $currentReference = null;
        $bufferReference = null;

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
                $chunks[] = [
                    'chunk_order' => $order++,
                    'chunk_text' => implode("\n\n", $buffer),
                    'reference_label' => $bufferReference,
                ];

                $buffer = [];
                $bufferLength = 0;
                $bufferReference = null;
            }

            $buffer[] = $clean;
            $bufferLength = $bufferLength === 0
                ? mb_strlen($clean, 'UTF-8')
                : $bufferLength + 2 + mb_strlen($clean, 'UTF-8');
            $bufferReference ??= $paragraphReference ?? $currentReference ?? $this->fallbackReference($clean);
        }

        if ($buffer !== []) {
            $chunks[] = [
                'chunk_order' => $order,
                'chunk_text' => implode("\n\n", $buffer),
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
        if (preg_match('/^(Art\.?\s*\d+[A-Za-z0-9º°\-\.]*)(.*)$/iu', $paragraph, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^(Livro|Titulo|Capitulo|Secao|Subsecao)\s+[A-Z0-9IVXLCDM\-]+/iu', $paragraph, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }

    private function fallbackReference(string $paragraph): string
    {
        $snippet = mb_substr($paragraph, 0, 90, 'UTF-8');

        return $snippet . (mb_strlen($paragraph, 'UTF-8') > 90 ? '...' : '');
    }
}
