<?php

namespace App\Models;

use App\Support\AiDocumentCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocumentChunk extends Model
{
    protected $table = 'ai_document_chunks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function globalDocument(): BelongsTo
    {
        return $this->belongsTo(AiGlobalDocument::class, 'ai_global_document_id');
    }

    public function clientAttachment(): BelongsTo
    {
        return $this->belongsTo(ClientAttachment::class, 'client_attachment_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'client_condominium_id');
    }

    public function clientCondominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'client_condominium_id');
    }

    public function effectiveSourceType(): string
    {
        $sourceType = trim((string) $this->source_type);
        if ($sourceType !== '') {
            return $sourceType;
        }

        if ($this->ai_global_document_id || $this->origin === 'global') {
            return AiDocumentCatalog::SOURCE_GLOBAL_DOCUMENT;
        }

        return AiDocumentCatalog::SOURCE_CONDOMINIUM_ATTACHMENT;
    }

    public function effectiveDocumentKind(): string
    {
        return trim((string) ($this->document_kind ?: $this->source_document_type ?: ''));
    }

    public function effectiveChunkIndex(): int
    {
        return (int) ($this->chunk_index ?: $this->chunk_order ?: 0);
    }

    public function effectiveTitle(): string
    {
        return trim((string) ($this->title ?: $this->reference_label ?: ''));
    }

    public function effectiveContent(): string
    {
        return trim((string) ($this->content ?: $this->chunk_text ?: ''));
    }

    public function effectiveSearchableContent(): string
    {
        $value = trim((string) $this->searchable_content);

        return $value !== '' ? $value : AiDocumentCatalog::searchableText($this->effectiveContent());
    }
}
