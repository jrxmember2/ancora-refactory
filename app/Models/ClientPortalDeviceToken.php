<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalDeviceToken extends Model
{
    protected $table = 'client_portal_device_tokens';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id');
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ClientPortalApiToken::class, 'client_portal_api_token_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
