<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessCase extends Model
{
    protected $table = 'process_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'is_private' => 'boolean',
            'claim_amount' => 'decimal:2',
            'claim_amount_date' => 'date',
            'provisioned_amount' => 'decimal:2',
            'provisioned_amount_date' => 'date',
            'court_paid_amount' => 'decimal:2',
            'court_paid_amount_date' => 'date',
            'process_cost_amount' => 'decimal:2',
            'process_cost_amount_date' => 'date',
            'sentence_amount' => 'decimal:2',
            'sentence_amount_date' => 'date',
            'closed_at' => 'date',
            'last_datajud_sync_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function statusOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'status_option_id'); }
    public function actionTypeOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'action_type_option_id'); }
    public function processTypeOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'process_type_option_id'); }
    public function client(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'client_entity_id'); }
    public function clientCondominium(): BelongsTo { return $this->belongsTo(ClientCondominium::class, 'client_condominium_id'); }
    public function adverse(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'adverse_entity_id'); }
    public function clientPositionOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'client_position_option_id'); }
    public function adversePositionOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'adverse_position_option_id'); }
    public function natureOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'nature_option_id'); }
    public function winProbabilityOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'win_probability_option_id'); }
    public function closureTypeOption(): BelongsTo { return $this->belongsTo(ProcessCaseOption::class, 'closure_type_option_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function phases(): HasMany { return $this->hasMany(ProcessCasePhase::class, 'process_case_id')->orderByDesc('phase_date')->orderByDesc('phase_time')->orderByDesc('created_at'); }
    public function attachments(): HasMany { return $this->hasMany(ProcessCaseAttachment::class, 'process_case_id')->orderByDesc('created_at'); }
}
