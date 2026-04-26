<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReimbursement extends Model
{
    protected $table = 'financial_reimbursements';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'reimbursed_at' => 'datetime',
            'amount' => 'decimal:2',
            'paid_by_office_amount' => 'decimal:2',
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

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'reimbursement_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinancialAttachment::class, 'owner_id')
            ->where('owner_type', 'reimbursement')
            ->latest('id');
    }
}
