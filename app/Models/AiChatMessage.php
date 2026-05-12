<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'tokens_total' => 'integer',
            'is_relevant' => 'boolean',
            'requires_legal_review' => 'boolean',
            'is_faq_candidate' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'ai_chat_conversation_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(AiChatMessageSource::class, 'ai_chat_message_id')->orderBy('id');
    }

    public function questionText(): string
    {
        $fromMeta = trim((string) data_get($this->meta_json, 'question'));
        if ($fromMeta !== '') {
            return $fromMeta;
        }

        if ($this->relationLoaded('conversation') && $this->conversation && $this->conversation->relationLoaded('messages')) {
            $message = $this->conversation->messages
                ->where('role', 'user')
                ->where('id', '<', $this->id)
                ->sortByDesc('id')
                ->first();

            return trim((string) ($message?->content ?? ''));
        }

        return (string) static::query()
            ->where('ai_chat_conversation_id', $this->ai_chat_conversation_id)
            ->where('role', 'user')
            ->where('id', '<', $this->id)
            ->orderByDesc('id')
            ->value('content');
    }

    public function errorText(): string
    {
        return trim((string) ($this->error_message ?: $this->error ?: ''));
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
