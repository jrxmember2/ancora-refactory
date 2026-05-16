<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientPortalApiToken extends Model
{
    protected $table = 'client_portal_api_tokens';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'abilities_json' => 'array',
            'context_json' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(ClientPortalDeviceToken::class, 'client_portal_api_token_id');
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function ($inner) {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
