<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaEventSync extends Model
{
    protected $table = 'agenda_event_syncs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AgendaEvent::class, 'agenda_event_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'connection_id');
    }
}
