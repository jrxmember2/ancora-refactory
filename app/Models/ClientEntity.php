<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientEntity extends Model
{
    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('display_name');
    }

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
            'birth_date' => 'date',
            'contract_end_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ownedUnits(): HasMany
    {
        return $this->hasMany(ClientUnit::class, 'owner_entity_id');
    }

    public function rentedUnits(): HasMany
    {
        return $this->hasMany(ClientUnit::class, 'tenant_entity_id');
    }

    public function unitPartyHistories(): HasMany
    {
        return $this->hasMany(ClientUnitPartyHistory::class, 'entity_id');
    }
}
