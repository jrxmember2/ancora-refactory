<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proposal extends Model
{
    protected $table = 'propostas';

    protected $fillable = [
        'proposal_year', 'proposal_seq', 'proposal_code', 'proposal_date', 'client_name', 'administradora_id', 'service_id',
        'proposal_total', 'closed_total', 'requester_name', 'requester_phone', 'contact_email', 'has_referral', 'referral_name',
        'send_method_id', 'response_status_id', 'refusal_reason', 'followup_date', 'validity_days', 'notes', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'proposal_date' => 'date',
            'followup_date' => 'date',
            'proposal_total' => 'decimal:2',
            'closed_total' => 'decimal:2',
            'has_referral' => 'boolean',
        ];
    }

    public function administradora(): BelongsTo { return $this->belongsTo(Administradora::class, 'administradora_id'); }
    public function servico(): BelongsTo { return $this->belongsTo(Servico::class, 'service_id'); }
    public function formaEnvio(): BelongsTo { return $this->belongsTo(FormaEnvio::class, 'send_method_id'); }
    public function statusRetorno(): BelongsTo { return $this->belongsTo(StatusRetorno::class, 'response_status_id'); }
    public function attachments(): HasMany { return $this->hasMany(ProposalAttachment::class, 'proposta_id'); }
    public function history(): HasMany { return $this->hasMany(ProposalHistory::class, 'proposta_id'); }
}
