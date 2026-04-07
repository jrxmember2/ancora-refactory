<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaCaseQuota extends Model
{
    protected $table = 'cobranca_case_quotas';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'original_amount' => 'decimal:2',
            'updated_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function cobrancaCase(): BelongsTo { return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id'); }
}
