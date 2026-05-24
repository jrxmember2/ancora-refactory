<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubApiToken extends Model
{
    protected $table = 'hub_api_tokens';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'abilities_json' => 'array',
            'biometric_enabled' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(HubDeviceToken::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(HubAppLoginLog::class);
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
