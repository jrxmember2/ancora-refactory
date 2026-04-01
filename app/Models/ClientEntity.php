<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientEntity extends Model
{
    protected $table = 'client_entities';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'primary_address_json' => 'array',
            'billing_address_json' => 'array',
            'phones_json' => 'array',
            'emails_json' => 'array',
            'shareholders_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
