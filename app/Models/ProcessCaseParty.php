<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessCaseParty extends Model
{
    protected $table = 'process_case_parties';

    protected $guarded = [];

    public function processCase(): BelongsTo
    {
        return $this->belongsTo(ProcessCase::class, 'process_case_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'entity_id');
    }
}
