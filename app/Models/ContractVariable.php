<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractVariable extends Model
{
    protected $table = 'contract_variables';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
