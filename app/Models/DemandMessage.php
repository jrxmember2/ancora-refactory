<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandMessage extends Model
{
    protected $table = 'demand_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function demand(): BelongsTo { return $this->belongsTo(Demand::class, 'demand_id'); }
    public function portalUser(): BelongsTo { return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function attachments(): HasMany { return $this->hasMany(DemandAttachment::class, 'message_id'); }

    public function senderName(): string
    {
        if ($this->sender_type === 'client') {
            return $this->portalUser?->name ?: 'Cliente';
        }

        return $this->user?->name ?: 'Equipe do escritorio';
    }
}
