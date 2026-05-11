<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    protected $table = 'ai_chat_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta_json' => 'array',
            'source_chunks_count' => 'integer',
            'token_estimate' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'ai_chat_conversation_id');
    }
}
