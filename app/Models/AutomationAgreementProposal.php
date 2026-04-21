<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationAgreementProposal extends Model
{
    protected $table = 'automation_agreement_proposals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_due_date' => 'date',
            'base_total' => 'decimal:2',
            'updated_total' => 'decimal:2',
            'calculation_memory' => 'array',
            'rules_snapshot' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AutomationSession::class, 'session_id');
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class, 'automation_agreement_proposal_id');
    }
}
