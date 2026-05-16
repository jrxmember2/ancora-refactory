<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalNotification extends Model
{
    protected $table = 'client_portal_notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'client_condominium_id');
    }
}
