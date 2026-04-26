<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialProcessCost extends Model
{
    protected $table = 'financial_process_costs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cost_date' => 'date',
            'amount' => 'decimal:2',
            'reimbursed_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessCase::class, 'process_id');
    }

    public function reimbursement(): BelongsTo
    {
        return $this->belongsTo(FinancialReimbursement::class, 'reimbursement_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(FinancialCostCenter::class, 'cost_center_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'process_cost_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialAttachment::class, 'owner_id')
            ->where('owner_type', 'process_cost')
            ->latest('id');
    }
}
