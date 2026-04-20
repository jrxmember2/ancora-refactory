<?php

namespace App\Support;

use App\Models\ClientPortalUser;
use App\Models\CobrancaCase;
use App\Models\Demand;
use App\Models\ProcessCase;
use Illuminate\Database\Eloquent\Builder;

class ClientPortalAccess
{
    public function scopeProcesses(Builder $query, ClientPortalUser $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            $condominiumIds = $user->accessibleCondominiumIds();
            if ($condominiumIds !== []) {
                $inner->orWhereIn('client_condominium_id', $condominiumIds);
            }

            if ($user->client_entity_id) {
                $inner->orWhere('client_entity_id', $user->client_entity_id);
            }

            if ($condominiumIds === [] && !$user->client_entity_id) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    public function scopeCobrancas(Builder $query, ClientPortalUser $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            $condominiumIds = $user->accessibleCondominiumIds();
            if ($condominiumIds !== []) {
                $inner->orWhereIn('condominium_id', $condominiumIds);
            }

            if ($user->client_entity_id) {
                $inner->orWhere('debtor_entity_id', $user->client_entity_id);
            }

            if ($condominiumIds === [] && !$user->client_entity_id) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    public function scopeDemands(Builder $query, ClientPortalUser $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
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
            || ($user->client_entity_id && (int) $case->client_entity_id === (int) $user->client_entity_id);
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
}
