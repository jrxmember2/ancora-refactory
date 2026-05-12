<?php

namespace App\Models;

use App\Support\AiDocumentCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessageSource extends Model
{
    protected $table = 'ai_chat_message_sources';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiChatMessage::class, 'ai_chat_message_id');
    }

    public function clientAttachment(): BelongsTo
    {
        return $this->belongsTo(ClientAttachment::class, 'client_attachment_id');
    }

    public function globalDocument(): BelongsTo
    {
        return $this->belongsTo(AiGlobalDocument::class, 'ai_global_document_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(AiDocumentChunk::class, 'chunk_id');
    }

    public function documentLabel(): string
    {
        $title = trim((string) $this->document_title);
        if ($title !== '') {
            return $title;
        }

        $kind = trim((string) $this->document_kind);
        if ($kind !== '') {
            return AiDocumentCatalog::documentKindLabel($kind);
        }

        return 'Documento';
    }
}
