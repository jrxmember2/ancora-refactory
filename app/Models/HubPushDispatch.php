<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubPushDispatch extends Model
{
    protected $table = 'hub_push_dispatches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(HubNotification::class, 'hub_notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(HubDeviceToken::class, 'hub_device_token_id');
    }
}
