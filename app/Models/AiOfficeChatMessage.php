<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiOfficeChatMessage extends Model
{
    protected $table = 'ai_office_chat_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'source_chunks_count' => 'integer',
            'token_estimate' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'tokens_total' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiOfficeChatConversation::class, 'ai_office_chat_conversation_id');
    }

    public function resolvedTokensTotal(): ?int
    {
        if ($this->tokens_total !== null) {
            return (int) $this->tokens_total;
        }

        if ($this->input_tokens !== null || $this->output_tokens !== null) {
            return (int) (($this->input_tokens ?? 0) + ($this->output_tokens ?? 0));
        }

        return $this->token_estimate !== null ? (int) $this->token_estimate : null;
    }
}
