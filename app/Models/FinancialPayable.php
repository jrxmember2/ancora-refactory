<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialPayable extends Model
{
    use SoftDeletes;

    protected $table = 'financial_payables';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'competence_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'supplier_entity_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(FinancialCostCenter::class, 'cost_center_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessCase::class, 'process_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'payable_id')->orderBy('transaction_date');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialAttachment::class, 'owner_id')
            ->where('owner_type', 'payable')
            ->latest('id');
    }
}
