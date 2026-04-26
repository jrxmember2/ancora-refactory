<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialStatement extends Model
{
    protected $table = 'financial_statements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'statement_date' => 'datetime',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'is_reconciled' => 'boolean',
            'payload_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function importLog(): BelongsTo
    {
        return $this->belongsTo(FinancialImportLog::class, 'import_log_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'statement_id');
    }
}
