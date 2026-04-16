<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientBlock extends Model
{
    protected $table = "client_condominium_blocks";

    protected $guarded = [];

    public $timestamps = false;

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, "condominium_id");
    }

    public function units(): HasMany
    {
        return $this->hasMany(ClientUnit::class, "block_id");
    }
}
