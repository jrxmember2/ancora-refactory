<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvolutionWebhookEvent extends Model
{
    protected $table = 'evolution_webhook_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'context' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
