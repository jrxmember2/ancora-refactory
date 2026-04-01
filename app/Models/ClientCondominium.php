<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCondominium extends Model
{
    protected $table = 'client_condominiums';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'has_blocks' => 'boolean',
            'is_active' => 'boolean',
            'address_json' => 'array',
            'bank_account_json' => 'array',
            'characteristics_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
