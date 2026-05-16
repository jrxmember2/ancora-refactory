<?php

namespace App\Services\Mobile;

use App\Models\ClientPortalUser;
use App\Models\Demand;
use App\Models\ProcessCase;
use App\Support\ClientPortalAccess;
use Illuminate\Support\Collection;

class ClientPortalAudienceResolver
{
    public function __construct(
        private readonly ClientPortalAccess $access,
    ) {
    }

    /** @return Collection<int, ClientPortalUser> */
    public function forProcess(ProcessCase $case): Collection
    {
        return ClientPortalUser::query()
            ->active()
            ->where('can_view_processes', true)
            ->with(['condominiums', 'condominium'])
            ->get()
            ->filter(fn (ClientPortalUser $user) => $this->access->canSeeProcess($user, $case))
            ->values();
    }

    /** @return Collection<int, ClientPortalUser> */
    public function forDemand(Demand $demand): Collection
    {
        return ClientPortalUser::query()
            ->active()
            ->where(function ($query) {
                $query->where('can_view_demands', true)
                    ->orWhere('can_open_demands', true);
            })
            ->with(['condominiums', 'condominium'])
            ->get()
            ->filter(fn (ClientPortalUser $user) => $this->access->canSeeDemand($user, $demand))
            ->values();
    }
}
