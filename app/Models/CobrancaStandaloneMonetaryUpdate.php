<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobrancaStandaloneMonetaryUpdate extends Model
{
    protected $table = 'cobranca_standalone_monetary_updates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'calculation_date' => 'date',
            'final_date' => 'date',
            'interest_rate_monthly' => 'decimal:4',
            'fine_percent' => 'decimal:4',
            'attorney_fee_value' => 'decimal:4',
            'costs_amount' => 'decimal:2',
            'costs_date' => 'date',
            'costs_corrected_amount' => 'decimal:2',
            'boleto_fee_total' => 'decimal:2',
            'boleto_cancellation_fee_total' => 'decimal:2',
            'abatement_amount' => 'decimal:2',
            'original_total' => 'decimal:2',
            'corrected_total' => 'decimal:2',
            'interest_total' => 'decimal:2',
            'fine_total' => 'decimal:2',
            'debit_total' => 'decimal:2',
            'attorney_fee_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'payload_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CobrancaStandaloneMonetaryUpdateItem::class, 'cobranca_standalone_monetary_update_id')
            ->orderBy('due_date')
            ->orderBy('item_order')
            ->orderBy('id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_entity_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
