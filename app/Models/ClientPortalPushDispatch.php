<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPortalPushDispatch extends Model
{
    protected $table = 'client_portal_push_dispatches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recipient_user_ids_json' => 'array',
            'recipient_snapshots_json' => 'array',
            'payload_json' => 'array',
            'queued_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
