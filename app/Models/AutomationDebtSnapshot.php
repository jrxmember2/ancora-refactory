<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationDebtSnapshot extends Model
{
    protected $table = 'automation_debt_snapshots';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_payload' => 'array',
            'calculation_memory' => 'array',
            'base_total' => 'decimal:2',
            'updated_total' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AutomationSession::class, 'session_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'unit_id');
    }

    public function cobrancaCase(): BelongsTo
    {
        return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id');
    }
}
