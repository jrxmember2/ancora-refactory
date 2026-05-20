<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Demand extends Model
{
    protected $table = 'demands';

    protected $guarded = [];

    public static function createWithGeneratedProtocol(array $attributes): self
    {
        return self::withProtocolLock(function () use ($attributes) {
            $attributes['protocol'] = self::generateNextProtocol();

            /** @var self $demand */
            $demand = self::query()->create($attributes);

            return $demand;
        });
    }

    protected function casts(): array
    {
        return [
            'last_external_message_at' => 'datetime',
            'last_internal_message_at' => 'datetime',
            'sla_started_at' => 'datetime',
            'closed_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function portalUser(): BelongsTo { return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id'); }
    public function entity(): BelongsTo { return $this->belongsTo(ClientEntity::class, 'client_entity_id'); }
    public function condominium(): BelongsTo { return $this->belongsTo(ClientCondominium::class, 'client_condominium_id'); }
    public function processCase(): BelongsTo { return $this->belongsTo(ProcessCase::class, 'process_case_id'); }
    public function cobrancaCase(): BelongsTo { return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id'); }
    public function automationSession(): BelongsTo { return $this->belongsTo(AutomationSession::class, 'automation_session_id'); }
    public function automationAgreementProposal(): BelongsTo { return $this->belongsTo(AutomationAgreementProposal::class, 'automation_agreement_proposal_id'); }
    public function category(): BelongsTo { return $this->belongsTo(DemandCategory::class, 'category_id'); }
    public function tag(): BelongsTo { return $this->belongsTo(DemandTag::class, 'demand_tag_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function messages(): HasMany { return $this->hasMany(DemandMessage::class, 'demand_id')->orderBy('created_at')->orderBy('id'); }
    public function publicMessages(): HasMany { return $this->messages()->where('is_internal', false); }
    public function attachments(): HasMany { return $this->hasMany(DemandAttachment::class, 'demand_id')->orderByDesc('created_at'); }

    public function clientName(): string
    {
        return $this->condominium?->name
            ?: ($this->entity?->display_name ?: ($this->portalUser?->displayClientName() ?: 'Cliente nao informado'));
    }

    public function internalStatusLabel(): string
    {
        return $this->tag?->name ?: (self::statusLabels()[$this->status] ?? (string) $this->status);
    }

    public function publicStatusLabel(): string
    {
        if ($this->tag && $this->tag->show_on_portal) {
            return $this->tag->publicLabel();
        }

        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function slaStatus(): string
    {
        if (!$this->sla_due_at || in_array($this->status, ['concluida', 'cancelada'], true)) {
            return 'none';
        }

        if ($this->sla_due_at->isPast()) {
            return 'overdue';
        }

        $startedAt = $this->sla_started_at ?: $this->created_at;
        if (!$startedAt || $startedAt->greaterThanOrEqualTo($this->sla_due_at)) {
            return 'ok';
        }

        $totalSeconds = max(1, $startedAt->diffInSeconds($this->sla_due_at, false));
        $remainingSeconds = now()->diffInSeconds($this->sla_due_at, false);

        return ($remainingSeconds / $totalSeconds) <= 0.1 ? 'at_risk' : 'ok';
    }

    public function slaStatusLabel(): string
    {
        return [
            'none' => 'Sem SLA',
            'ok' => 'No prazo',
            'at_risk' => 'A vencer',
            'overdue' => 'Vencido',
        ][$this->slaStatus()] ?? 'Sem SLA';
    }

    public function slaProgressPercent(): int
    {
        if (!$this->sla_due_at) {
            return 0;
        }

        $startedAt = $this->sla_started_at ?: $this->created_at;
        if (!$startedAt || $startedAt->greaterThanOrEqualTo($this->sla_due_at)) {
            return 0;
        }

        $totalSeconds = max(1, $startedAt->diffInSeconds($this->sla_due_at, false));
        $elapsedSeconds = max(0, $startedAt->diffInSeconds(now(), false));

        return min(100, (int) round(($elapsedSeconds / $totalSeconds) * 100));
    }

    public static function statusLabels(): array
    {
        return [
            'aberta' => 'Aberta',
            'em_triagem' => 'Em triagem',
            'em_andamento' => 'Em andamento',
            'aguardando_cliente' => 'Aguardando cliente',
            'aguardando_formalizacao_acordo' => 'Aguardando formalizacao do acordo',
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

    private static function generateNextProtocol(?int $year = null): string
    {
        $year ??= now()->year;
        $latestProtocol = self::query()
            ->where('protocol', 'like', sprintf('DEM-%d-%%', $year))
            ->orderByRaw("CAST(SUBSTRING_INDEX(protocol, '-', -1) AS UNSIGNED) DESC")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('protocol');

        $sequence = 1;
        if (is_string($latestProtocol) && preg_match('/^DEM-\d{4}-(\d+)$/', $latestProtocol, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('DEM-%d-%05d', $year, $sequence);
    }

    private static function withProtocolLock(callable $callback): mixed
    {
        $year = now()->year;
        $lockName = sprintf('demands-protocol-%d', $year);
        $result = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockName]);
        $acquired = (int) ($result->acquired ?? 0);

        if ($acquired !== 1) {
            throw new RuntimeException('Nao foi possivel reservar o proximo protocolo da demanda.');
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }
}
