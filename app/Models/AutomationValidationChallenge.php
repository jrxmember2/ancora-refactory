<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationValidationChallenge extends Model
{
    protected $table = 'automation_validation_challenges';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'displayed_options' => 'array',
            'solved_at' => 'datetime',
            'failed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AutomationSession::class, 'session_id');
    }
}
