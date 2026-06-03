<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendaEventReminder extends Model
{
    protected $table = 'agenda_event_reminders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'minutes_before' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AgendaEvent::class, 'agenda_event_id');
    }
}
