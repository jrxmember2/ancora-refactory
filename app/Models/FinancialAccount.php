<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $table = 'financial_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(FinancialReceivable::class, 'account_id');
    }

    public function payables(): HasMany
    {
        return $this->hasMany(FinancialPayable::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'account_id');
    }

    public function destinationTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'destination_account_id');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(FinancialStatement::class, 'account_id');
    }
}
