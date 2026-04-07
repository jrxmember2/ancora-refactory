<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobrancaCase extends Model
{
    protected $table = 'cobranca_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'agreement_total' => 'decimal:2',
            'billing_date' => 'date',
            'entry_due_date' => 'date',
            'entry_amount' => 'decimal:2',
            'fees_amount' => 'decimal:2',
            'calc_base_date' => 'date',
            'last_progress_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo { return $this->belongsTo(ClientCondominium::class, 'condominium_id'); }
    public function block(): BelongsTo { return $this->belongsTo(ClientBlock::class, 'block_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(ClientUnit::class, 'unit_id'); }
    public function debtor(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'debtor_entity_id'); }
    public function contacts(): HasMany { return $this->hasMany(CobrancaCaseContact::class, 'cobranca_case_id')->orderByDesc('is_primary')->orderBy('id'); }
    public function quotas(): HasMany { return $this->hasMany(CobrancaCaseQuota::class, 'cobranca_case_id')->orderBy('due_date'); }
    public function installments(): HasMany { return $this->hasMany(CobrancaCaseInstallment::class, 'cobranca_case_id')->orderBy('due_date'); }
    public function timeline(): HasMany { return $this->hasMany(CobrancaCaseTimeline::class, 'cobranca_case_id')->orderByDesc('created_at'); }
    public function attachments(): HasMany { return $this->hasMany(CobrancaCaseAttachment::class, 'cobranca_case_id')->orderByDesc('created_at'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
