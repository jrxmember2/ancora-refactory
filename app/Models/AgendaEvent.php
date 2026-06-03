<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgendaEvent extends Model
{
    use SoftDeletes;

    protected $table = 'agenda_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_fatal' => 'boolean',
            'all_day' => 'boolean',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'reminder_minutes' => 'integer',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessCase::class, 'process_id');
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class, 'demand_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'aberto');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'aberto')->where('start_at', '<', now());
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->where('status', 'aberto')
            ->whereBetween('start_at', [now(), now()->copy()->addDays(max(1, $days))->endOfDay()]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId) {
            $inner->where('responsible_user_id', $userId)
                ->orWhere('requester_user_id', $userId);
        });
    }

    public function isOverdue(): bool
    {
        return $this->status === 'aberto' && $this->start_at && $this->start_at->isPast();
    }

    public function hasColor(): bool
    {
        return \App\Support\ColorContrast::normalizeHex($this->color) !== null;
    }

    public function textColor(): string
    {
        return \App\Support\ColorContrast::idealTextColor($this->color);
    }

    public function effectiveStatus(): string
    {
        return $this->isOverdue() ? 'atrasado' : (string) $this->status;
    }
}
