<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaStandaloneMonetaryUpdateItem extends Model
{
    protected $table = 'cobranca_standalone_monetary_update_items';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'item_order' => 'integer',
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
        return $this->belongsTo(CobrancaStandaloneMonetaryUpdate::class, 'cobranca_standalone_monetary_update_id');
    }
}
