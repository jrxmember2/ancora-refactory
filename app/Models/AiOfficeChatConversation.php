<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiOfficeChatConversation extends Model
{
    protected $table = 'ai_office_chat_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'client_condominium_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiOfficeChatMessage::class, 'ai_office_chat_conversation_id')->oldest('created_at')->oldest('id');
    }

    public function displayTitle(): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : 'Nova conversa';
    }

    public function scopeLabel(): string
    {
        return $this->scope_type === 'legal_base'
            ? 'Base Legal Global'
            : ($this->condominium?->name ?: 'Condominio');
    }
}
