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
            if ($user->client_condominium_id) {
                $inner->orWhere('client_condominium_id', $user->client_condominium_id);
            }

            if ($user->client_entity_id) {
                $inner->orWhere('client_entity_id', $user->client_entity_id);
            }

            if (!$user->client_condominium_id && !$user->client_entity_id) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    public function scopeCobrancas(Builder $query, ClientPortalUser $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            if ($user->client_condominium_id) {
                $inner->orWhere('condominium_id', $user->client_condominium_id);
            }

            if ($user->client_entity_id) {
                $inner->orWhere('debtor_entity_id', $user->client_entity_id);
            }

            if (!$user->client_condominium_id && !$user->client_entity_id) {
                $inner->whereRaw('1 = 0');
            }
        });
    }

    public function scopeDemands(Builder $query, ClientPortalUser $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('client_portal_user_id', $user->id);

            if ($user->client_condominium_id) {
                $inner->orWhere('client_condominium_id', $user->client_condominium_id);
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

        return ($user->client_condominium_id && (int) $case->client_condominium_id === (int) $user->client_condominium_id)
            || ($user->client_entity_id && (int) $case->client_entity_id === (int) $user->client_entity_id);
    }

    public function canSeeCobranca(ClientPortalUser $user, CobrancaCase $case): bool
    {
        if (!$user->can_view_cobrancas) {
            return false;
        }

        return ($user->client_condominium_id && (int) $case->condominium_id === (int) $user->client_condominium_id)
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
            || ($user->client_condominium_id && (int) $demand->client_condominium_id === (int) $user->client_condominium_id)
            || ($user->client_entity_id && (int) $demand->client_entity_id === (int) $user->client_entity_id);
    }
}
