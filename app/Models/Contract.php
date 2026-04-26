<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $table = 'contracts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'indefinite_term' => 'boolean',
            'contract_value' => 'decimal:2',
            'monthly_value' => 'decimal:2',
            'total_value' => 'decimal:2',
            'next_adjustment_date' => 'date',
            'penalty_value' => 'decimal:2',
            'penalty_percentage' => 'decimal:2',
            'generate_financial_entries' => 'boolean',
            'final_pdf_generated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContractCategory::class, 'category_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'condominium_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'unit_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
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

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class, 'contract_id')->orderByDesc('version_number');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class, 'contract_id')->orderByDesc('created_at');
    }
}
