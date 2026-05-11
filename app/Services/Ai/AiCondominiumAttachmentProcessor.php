<?php

namespace App\Services\Ai;

use App\Models\AiDocumentChunk;
use App\Models\ClientAttachment;
use App\Support\AiDocumentCatalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AiCondominiumAttachmentProcessor
{
    public function __construct(
        private readonly DocumentTextExtractor $extractor,
        private readonly DocumentChunker $chunker,
    ) {
    }

    /**
     * @return array{status:string,chunks:int,text_length:int,document_kind:string}
     */
    public function process(ClientAttachment $attachment): array
    {
        if ($attachment->related_type !== 'condominium') {
            throw new RuntimeException('A IA desta fase processa apenas anexos de condominios.');
        }

        $documentKind = $attachment->condominiumDocumentKind();
        if ($documentKind === null) {
            throw new RuntimeException('Este anexo nao pertence aos documentos estruturados de Convencao, Regimento ou ATA.');
        }

        if (!$attachment->isDocx()) {
            throw new RuntimeException('Somente anexos DOCX podem ser processados para IA nesta fase.');
        }

        $path = $attachment->absolutePath();
        if (!is_string($path) || !is_file($path)) {
            throw new RuntimeException('O arquivo salvo deste anexo nao foi encontrado no servidor.');
        }

        try {
            $text = $this->extractor->extract($path);
            $chunks = $this->chunker->chunk($text);

            if ($chunks === []) {
                throw new RuntimeException('Nao foi possivel gerar blocos pesquisaveis a partir deste DOCX.');
            }

            DB::transaction(function () use ($attachment, $documentKind, $chunks): void {
                $this->deactivateChunksForAttachment($attachment);

                foreach ($chunks as $chunk) {
                    AiDocumentChunk::query()->create([
                        'origin' => 'condominium',
                        'source_type' => AiDocumentCatalog::SOURCE_CONDOMINIUM_ATTACHMENT,
                        'source_document_type' => $documentKind,
                        'ai_global_document_id' => null,
                        'client_attachment_id' => (int) $attachment->id,
                        'condominium_id' => (int) $attachment->related_id,
                        'chunk_order' => (int) $chunk['chunk_index'],
                        'reference_label' => $chunk['reference_label'],
                        'chunk_text' => $chunk['content'],
                        'client_condominium_id' => (int) $attachment->related_id,
                        'document_kind' => $documentKind,
                        'document_date' => $attachment->document_date?->toDateString(),
                        'chunk_index' => (int) $chunk['chunk_index'],
                        'title' => $chunk['title'],
                        'content' => $chunk['content'],
                        'searchable_content' => $chunk['searchable_content'],
                        'is_active' => true,
                    ]);
                }

                $attachment->forceFill([
                    'ai_processing_status' => AiDocumentCatalog::STATUS_PROCESSED,
                    'ai_processing_error' => null,
                    'ai_processed_at' => now(),
                ])->save();
            });

            return [
                'status' => AiDocumentCatalog::STATUS_PROCESSED,
                'chunks' => count($chunks),
                'text_length' => mb_strlen($text, 'UTF-8'),
                'document_kind' => $documentKind,
            ];
        } catch (Throwable $exception) {
            $attachment->forceFill([
                'ai_processing_status' => AiDocumentCatalog::STATUS_ERROR,
                'ai_processing_error' => mb_substr(trim($exception->getMessage()), 0, 5000, 'UTF-8'),
                'ai_processed_at' => null,
            ])->save();

            throw $exception;
        }
    }

    public function deactivateChunksForAttachment(ClientAttachment $attachment): void
    {
        AiDocumentChunk::query()
            ->where('client_attachment_id', (int) $attachment->id)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }
}
