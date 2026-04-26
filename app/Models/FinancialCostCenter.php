<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCostCenter extends Model
{
    protected $table = 'financial_cost_centers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(FinancialReceivable::class, 'cost_center_id');
    }

    public function payables(): HasMany
    {
        return $this->hasMany(FinancialPayable::class, 'cost_center_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'cost_center_id');
    }
}
