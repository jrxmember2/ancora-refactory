<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarSubscription extends Model
{
    protected $table = 'calendar_subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'connection_id');
    }

    public function isExpiringSoon(int $withinHours = 24): bool
    {
        return $this->expires_at === null || $this->expires_at->lte(now()->addHours($withinHours));
    }
}
