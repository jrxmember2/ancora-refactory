<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalAppLoginLog extends Model
{
    protected $table = 'client_portal_app_login_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
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
}
