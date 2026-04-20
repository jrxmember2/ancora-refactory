<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Demand extends Model
{
    protected $table = 'demands';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_external_message_at' => 'datetime',
            'last_internal_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo { return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id'); }
    public function entity(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'client_entity_id'); }
    public function condominium(): BelongsTo { return $this->belongsTo(ClientCondominium::class, 'client_condominium_id'); }
    public function processCase(): BelongsTo { return $this->belongsTo(ProcessCase::class, 'process_case_id'); }
    public function cobrancaCase(): BelongsTo { return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id'); }
    public function category(): BelongsTo { return $this->belongsTo(DemandCategory::class, 'category_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function messages(): HasMany { return $this->hasMany(DemandMessage::class, 'demand_id')->orderBy('created_at')->orderBy('id'); }
    public function publicMessages(): HasMany { return $this->messages()->where('is_internal', false); }
    public function attachments(): HasMany { return $this->hasMany(DemandAttachment::class, 'demand_id')->orderByDesc('created_at'); }

    public function clientName(): string
    {
        return $this->condominium?->name
            ?: ($this->entity?->display_name ?: ($this->portalUser?->displayClientName() ?: 'Cliente nao informado'));
    }

    public static function statusLabels(): array
    {
        return [
            'aberta' => 'Aberta',
            'em_triagem' => 'Em triagem',
            'em_andamento' => 'Em andamento',
            'aguardando_cliente' => 'Aguardando cliente',
            'concluida' => 'Concluida',
            'cancelada' => 'Cancelada',
        ];
    }

    public static function priorityLabels(): array
    {
        return [
            'baixa' => 'Baixa',
            'normal' => 'Normal',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ];
    }
}
