<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialInstallment extends Model
{
    protected $table = 'financial_installments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function parentReceivable(): BelongsTo
    {
        return $this->belongsTo(FinancialReceivable::class, 'parent_receivable_id');
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(FinancialReceivable::class, 'receivable_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'installment_id');
    }
}
