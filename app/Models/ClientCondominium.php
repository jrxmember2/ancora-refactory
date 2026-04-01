<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function syndic(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'syndico_entity_id'); }
    public function administradora(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'administradora_entity_id'); }
    public function type(): BelongsTo { return $this->belongsTo(ClientType::class, 'condominium_type_id'); }
    public function blocks(): HasMany { return $this->hasMany(ClientBlock::class, 'condominium_id')->orderBy('sort_order')->orderBy('name'); }
    public function units(): HasMany { return $this->hasMany(ClientUnit::class, 'condominium_id'); }
}
