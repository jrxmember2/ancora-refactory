<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPortalUser extends Model
{
    use SoftDeletes;

    protected $table = 'client_portal_users';

    protected $guarded = [];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'can_view_processes' => 'boolean',
            'can_view_cobrancas' => 'boolean',
            'can_open_demands' => 'boolean',
            'can_view_demands' => 'boolean',
            'can_view_documents' => 'boolean',
            'can_view_financial_summary' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_entity_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'client_condominium_id');
    }

    public function condominiums(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientCondominium::class,
            'client_portal_user_condominiums',
            'client_portal_user_id',
            'client_condominium_id'
        )->withTimestamps()->orderBy('name');
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class, 'client_portal_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function canPortal(string $permission): bool
    {
        return (bool) $this->{$permission};
    }

    public function displayClientName(): string
    {
        $condominiums = $this->accessibleCondominiums();

        if ($condominiums->count() === 1) {
            return (string) $condominiums->first()->name;
        }

        if ($condominiums->count() > 1) {
            return $condominiums->count() . ' condomínios vinculados';
        }

        return $this->entity?->display_name ?: 'Cliente';
    }

    public function portalCondominiumNames(int $limit = 3): string
    {
        $condominiums = $this->accessibleCondominiums();
        if ($condominiums->isEmpty()) {
            return '';
        }

        $names = $condominiums->pluck('name')->take($limit)->implode(', ');
        $remaining = $condominiums->count() - $limit;

        return $remaining > 0 ? "{$names} +{$remaining}" : $names;
    }

    public function accessibleCondominiumIds(): array
    {
        return $this->accessibleCondominiums()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function accessibleCondominiums()
    {
        $condominiums = $this->relationLoaded('condominiums')
            ? $this->condominiums
            : $this->condominiums()->get();

        if ($this->client_condominium_id && !$condominiums->contains('id', (int) $this->client_condominium_id)) {
            $legacy = $this->relationLoaded('condominium')
                ? $this->condominium
                : $this->condominium()->first();

            if ($legacy) {
                $condominiums->push($legacy);
            }
        }

        return $condominiums->unique('id')->sortBy('name')->values();
    }
}
