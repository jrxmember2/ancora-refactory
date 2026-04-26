<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractCategory extends Model
{
    protected $table = 'contract_categories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class, 'category_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'category_id');
    }
}
