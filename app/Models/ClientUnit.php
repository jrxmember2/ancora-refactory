<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientUnit extends Model
{
    protected $table = 'client_units';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo { return $this->belongsTo(ClientCondominium::class, 'condominium_id'); }
    public function block(): BelongsTo { return $this->belongsTo(ClientBlock::class, 'block_id'); }
    public function type(): BelongsTo { return $this->belongsTo(ClientType::class, 'unit_type_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'owner_entity_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'tenant_entity_id'); }
}
