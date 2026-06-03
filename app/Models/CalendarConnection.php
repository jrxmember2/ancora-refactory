<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarConnection extends Model
{
    protected $table = 'calendar_connections';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(AgendaEventSync::class, 'connection_id');
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
