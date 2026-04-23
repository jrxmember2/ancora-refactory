<?php

namespace App\Support;

use App\Models\ClientPortalUser;
use App\Models\CobrancaCase;
use App\Models\Demand;
use App\Models\ProcessCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ClientPortalAccess
{
    public function scopeProcesses(Builder $query, ClientPortalUser $user, ?int $selectedCondominiumId = null): Builder
    {
        return $query->where(function (Builder $inner) use ($user, $selectedCondominiumId) {
            $condominiumIds = $this->filteredCondominiumIds($user, $selectedCondominiumId);
            if ($selectedCondominiumId && $condominiumIds === []) {
                $inner->whereRaw('1 = 0');
                return;
            }

            if ($condominiumIds !== []) {
                $condominiumNames = $user->accessibleCondominiums()
                    ->whereIn('id', $condominiumIds)
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all();

                $inner->orWhereIn('client_condominium_id', $condominiumIds)
                    ->orWhereIn('client_name_snapshot', $condominiumNames)
                    ->orWhereIn('adverse_name', $condominiumNames);

                if ($this->processHasAdverseCondominiumColumn()) {
                    $inner->orWhereIn('adverse_condominium_id', $condominiumIds);
                }
            }

            if (!$selectedCondominiumId && $user->client_entity_id) {
                $inner->orWhere('client_entity_id', $user->client_entity_id);
                $inner->orWhere('adverse_entity_id', $user->client_entity_id);
            }

            if ($condominiumIds === [] && !$user->client_entity_id) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    public function scopeCobrancas(Builder $query, ClientPortalUser $user, ?int $selectedCondominiumId = null): Builder
    {
        return $query->where(function (Builder $inner) use ($user, $selectedCondominiumId) {
            $condominiumIds = $this->filteredCondominiumIds($user, $selectedCondominiumId);
            if ($selectedCondominiumId && $condominiumIds === []) {
                $inner->whereRaw('1 = 0');
                return;
            }

            if ($condominiumIds !== []) {
                $inner->orWhereIn('condominium_id', $condominiumIds);
            }

            if (!$selectedCondominiumId && $user->client_entity_id) {
                $inner->orWhere('debtor_entity_id', $user->client_entity_id);
            }

            if ($condominiumIds === [] && !$user->client_entity_id) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    public function scopeDemands(Builder $query, ClientPortalUser $user, ?int $selectedCondominiumId = null): Builder
    {
        return $query->where(function (Builder $inner) use ($user, $selectedCondominiumId) {
            if ($selectedCondominiumId) {
                if (!in_array($selectedCondominiumId, $user->accessibleCondominiumIds(), true)) {
                    $inner->whereRaw('1 = 0');
                    return;
                }

                $inner->where('client_condominium_id', $selectedCondominiumId);
                return;
            }

            $inner->where('client_portal_user_id', $user->id);

            $condominiumIds = $user->accessibleCondominiumIds();
            if ($condominiumIds !== []) {
                $inner->orWhereIn('client_condominium_id', $condominiumIds);
            }

            if ($user->client_entity_id) {
                $inner->orWhere('client_entity_id', $user->client_entity_id);
            }
        });
    }

    public function canSeeProcess(ClientPortalUser $user, ProcessCase $case): bool
    {
        if (!$user->can_view_processes) {
            return false;
        }

        $condominiumIds = $user->accessibleCondominiumIds();

        return (in_array((int) $case->client_condominium_id, $condominiumIds, true))
            || (in_array((int) ($case->adverse_condominium_id ?? 0), $condominiumIds, true))
            || ($user->client_entity_id && (int) $case->client_entity_id === (int) $user->client_entity_id)
            || ($user->client_entity_id && (int) $case->adverse_entity_id === (int) $user->client_entity_id)
            || ($condominiumIds !== [] && in_array((string) $case->client_name_snapshot, $user->accessibleCondominiums()->pluck('name')->all(), true))
            || ($condominiumIds !== [] && in_array((string) $case->adverse_name, $user->accessibleCondominiums()->pluck('name')->all(), true));
    }

    public function canSeeCobranca(ClientPortalUser $user, CobrancaCase $case): bool
    {
        if (!$user->can_view_cobrancas) {
            return false;
        }

        $condominiumIds = $user->accessibleCondominiumIds();

        return (in_array((int) $case->condominium_id, $condominiumIds, true))
            || ($user->client_entity_id && (int) $case->debtor_entity_id === (int) $user->client_entity_id);
    }

    public function canSeeDemand(ClientPortalUser $user, Demand $demand): bool
    {
        if (!$user->can_view_demands && !$user->can_open_demands) {
            return false;
        }

        if (!$user->can_view_demands) {
            return (int) $demand->client_portal_user_id === (int) $user->id;
        }

        return (int) $demand->client_portal_user_id === (int) $user->id
            || (in_array((int) $demand->client_condominium_id, $user->accessibleCondominiumIds(), true))
            || ($user->client_entity_id && (int) $demand->client_entity_id === (int) $user->client_entity_id);
    }

    private function filteredCondominiumIds(ClientPortalUser $user, ?int $selectedCondominiumId): array
    {
        $condominiumIds = $user->accessibleCondominiumIds();
        if (!$selectedCondominiumId) {
            return $condominiumIds;
        }

        return in_array($selectedCondominiumId, $condominiumIds, true) ? [$selectedCondominiumId] : [];
    }

    private function processHasAdverseCondominiumColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            return $hasColumn = Schema::hasColumn('process_cases', 'adverse_condominium_id');
        } catch (\Throwable) {
            return $hasColumn = false;
        }
    }
}
