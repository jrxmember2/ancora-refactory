<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaMonetaryUpdateItem extends Model
{
    protected $table = 'cobranca_monetary_update_items';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'original_amount' => 'decimal:2',
            'correction_factor' => 'decimal:10',
            'corrected_amount' => 'decimal:2',
            'interest_months' => 'decimal:4',
            'interest_percent' => 'decimal:4',
            'interest_amount' => 'decimal:2',
            'fine_percent' => 'decimal:4',
            'fine_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function monetaryUpdate(): BelongsTo
    {
        return $this->belongsTo(CobrancaMonetaryUpdate::class, 'cobranca_monetary_update_id');
    }

    public function quota(): BelongsTo
    {
        return $this->belongsTo(CobrancaCaseQuota::class, 'cobranca_case_quota_id');
    }
}
