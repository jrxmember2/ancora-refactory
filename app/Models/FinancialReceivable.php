<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialReceivable extends Model
{
    use SoftDeletes;

    protected $table = 'financial_receivables';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'competence_date' => 'date',
            'original_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'correction_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'due_date' => 'date',
            'received_at' => 'datetime',
            'last_collection_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(FinancialCostCenter::class, 'cost_center_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'condominium_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'unit_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessCase::class, 'process_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'receivable_id')->orderBy('transaction_date');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FinancialInstallment::class, 'parent_receivable_id')->orderBy('installment_number');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialAttachment::class, 'owner_id')
            ->where('owner_type', 'receivable')
            ->latest('id');
    }
}
