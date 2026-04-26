<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReconciliation extends Model
{
    protected $table = 'financial_reconciliations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'matched_amount' => 'decimal:2',
            'reconciled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(FinancialStatement::class, 'statement_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
