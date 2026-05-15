<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvolutionMessageLog extends Model
{
    protected $table = 'evolution_message_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_status_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function automationSession(): BelongsTo
    {
        return $this->belongsTo(AutomationSession::class, 'automation_session_id');
    }

    public function processCase(): BelongsTo
    {
        return $this->belongsTo(ProcessCase::class, 'process_case_id');
    }

    public function processCasePhase(): BelongsTo
    {
        return $this->belongsTo(ProcessCasePhase::class, 'process_case_phase_id');
    }

    public function cobrancaCase(): BelongsTo
    {
        return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id');
    }
}
