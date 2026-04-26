<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    protected $table = 'financial_categories';

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
        return $this->hasMany(FinancialReceivable::class, 'category_id');
    }

    public function payables(): HasMany
    {
        return $this->hasMany(FinancialPayable::class, 'category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'category_id');
    }
}
