<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinancialBillingService
{
    public function __construct(
        private readonly ContractFinancialService $contractFinancialService,
    ) {
    }

    public function contractsReadyForBilling(): Collection
    {
        return Contract::query()
            ->with(['client', 'condominium', 'responsible'])
            ->where('generate_financial_entries', true)
            ->whereIn('status', ['ativo', 'assinado', 'aguardando_assinatura'])
            ->orderBy('title')
            ->get();
    }

    public function generateForContract(Contract $contract, Carbon $from, Carbon $to, ?int $userId = null): array
    {
        return $this->contractFinancialService->createMissingFinancialEntriesForPeriod(
            $contract,
            $from,
            $to,
            $userId
        );
    }
}
