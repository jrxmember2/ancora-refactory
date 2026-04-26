<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    protected $table = 'financial_transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'datetime',
            'amount' => 'decimal:2',
            'reconciled_at' => 'datetime',
            'metadata_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'destination_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(FinancialCostCenter::class, 'cost_center_id');
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(FinancialReceivable::class, 'receivable_id');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(FinancialPayable::class, 'payable_id');
    }

    public function reimbursement(): BelongsTo
    {
        return $this->belongsTo(FinancialReimbursement::class, 'reimbursement_id');
    }

    public function processCost(): BelongsTo
    {
        return $this->belongsTo(FinancialProcessCost::class, 'process_cost_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(FinancialInstallment::class, 'installment_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
