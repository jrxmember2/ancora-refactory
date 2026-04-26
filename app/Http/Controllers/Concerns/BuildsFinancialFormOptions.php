<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\Contract;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialCostCenter;
use App\Models\ProcessCase;
use App\Models\User;
use App\Support\Financeiro\FinancialCatalog;
use App\Support\Financeiro\FinancialValue;
use Illuminate\Http\Request;

trait BuildsFinancialFormOptions
{
    protected function financialFormOptions(): array
    {
        return [
            'clients' => ClientEntity::query()->where('is_active', 1)->orderBy('display_name')->get(['id', 'display_name', 'cpf_cnpj']),
            'condominiums' => ClientCondominium::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'units' => ClientUnit::query()->with(['condominium', 'block'])->orderBy('unit_number')->get(),
            'contracts' => Contract::query()->orderByDesc('id')->limit(500)->get(['id', 'code', 'title', 'client_id', 'condominium_id']),
            'processes' => class_exists(ProcessCase::class)
                ? ProcessCase::query()->orderByDesc('id')->limit(500)->get(['id', 'process_number', 'client_name_snapshot'])
                : collect(),
            'users' => User::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'categories' => FinancialCategory::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'costCenters' => FinancialCostCenter::query()->where('is_active', true)->orderBy('name')->get(),
            'accounts' => FinancialAccount::query()->where('is_active', true)->orderByDesc('is_primary')->orderBy('name')->get(),
            'receivableStatuses' => FinancialCatalog::receivableStatuses(),
            'payableStatuses' => FinancialCatalog::payableStatuses(),
            'transactionTypes' => FinancialCatalog::transactionTypes(),
            'accountTypes' => FinancialCatalog::accountTypes(),
            'paymentMethods' => FinancialCatalog::paymentMethods(),
            'billingTypes' => FinancialCatalog::billingTypes(),
            'recurrences' => FinancialCatalog::recurrences(),
            'collectionStages' => FinancialCatalog::collectionStages(),
            'reimbursementStatuses' => FinancialCatalog::reimbursementStatuses(),
            'processCostStatuses' => FinancialCatalog::processCostStatuses(),
            'dreGroups' => FinancialCatalog::dreGroups(),
        ];
    }

    protected function moneyToDecimal(mixed $value, float $fallback = 0.0): float
    {
        return FinancialValue::decimalFromInput($value) ?? $fallback;
    }

    protected function intOrNull(mixed $value): ?int
    {
        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    protected function boolFromRequest(Request $request, string $key): bool
    {
        return $request->boolean($key);
    }
}
