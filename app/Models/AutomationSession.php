<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationSession extends Model
{
    protected $table = 'automation_sessions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'last_interaction_at' => 'datetime',
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
            'interlocutor_confirmed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'condominium_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(ClientBlock::class, 'block_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'unit_id');
    }

    public function cobrancaCase(): BelongsTo
    {
        return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id');
    }

    public function validatedPerson(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'validated_person_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AutomationSessionMessage::class, 'session_id')->orderBy('created_at')->orderBy('id');
    }

    public function validationChallenges(): HasMany
    {
        return $this->hasMany(AutomationValidationChallenge::class, 'session_id')->latest('id');
    }

    public function debtSnapshots(): HasMany
    {
        return $this->hasMany(AutomationDebtSnapshot::class, 'session_id')->latest('id');
    }

    public function agreementProposals(): HasMany
    {
        return $this->hasMany(AutomationAgreementProposal::class, 'session_id')->latest('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AutomationAuditLog::class, 'session_id')->latest('id');
    }
}
