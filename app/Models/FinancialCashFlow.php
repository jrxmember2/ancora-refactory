<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialCashFlow extends Model
{
    protected $table = 'financial_cash_flow';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'movement_date' => 'datetime',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(FinancialReceivable::class, 'receivable_id');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(FinancialPayable::class, 'payable_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }
}
