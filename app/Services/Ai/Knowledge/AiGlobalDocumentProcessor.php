<?php

namespace App\Services\Ai\Knowledge;

use App\Models\AiGlobalDocument;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiGlobalDocumentProcessor
{
    public function __construct(
        private readonly DocxTextExtractor $extractor,
        private readonly AiTextChunker $chunker,
    ) {
    }

    /**
     * @return array{status:string,chunks:int,text_length:int}
     */
    public function process(AiGlobalDocument $document): array
    {
        if (!$document->isDocx()) {
            throw new \RuntimeException('Somente arquivos DOCX podem ser processados nesta fase da Base Legal Global.');
        }

        $path = $document->absolutePath();
        if (!is_string($path) || !is_file($path)) {
            throw new \RuntimeException('O arquivo salvo deste documento nao foi encontrado no servidor.');
        }

        try {
            $text = $this->extractor->extract($path);
            $chunks = $this->chunker->chunk($text);

            if ($chunks === []) {
                throw new \RuntimeException('Nao foi possivel gerar blocos pesquisaveis a partir do DOCX enviado.');
            }

            DB::transaction(function () use ($document, $text, $chunks): void {
                $document->chunks()->delete();

                $document->forceFill([
                    'extracted_text' => $text,
                    'processing_status' => 'processed',
                    'processing_error' => null,
                ])->save();

                foreach ($chunks as $chunk) {
                    $document->chunks()->create([
                        'origin' => 'global',
                        'source_type' => 'global_document',
                        'source_document_type' => (string) $document->document_type,
                        'chunk_order' => (int) $chunk['chunk_order'],
                        'reference_label' => $chunk['reference_label'],
                        'chunk_text' => $chunk['chunk_text'],
                        'is_active' => (bool) $document->is_active,
                    ]);
                }
            });

            return [
                'status' => 'processed',
                'chunks' => count($chunks),
                'text_length' => mb_strlen($text, 'UTF-8'),
            ];
        } catch (Throwable $exception) {
            $document->forceFill([
                'processing_status' => 'error',
                'processing_error' => mb_substr(trim($exception->getMessage()), 0, 5000, 'UTF-8'),
            ])->save();

            throw $exception;
        }
    }
}
