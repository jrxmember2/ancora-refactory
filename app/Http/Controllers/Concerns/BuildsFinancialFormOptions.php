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
use Illuminate\Support\Str;

trait BuildsFinancialFormOptions
{
    protected function financialFormOptions(): array
    {
        $categories = FinancialCategory::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $costCenters = FinancialCostCenter::query()->where('is_active', true)->orderBy('name')->get();
        $categoryLookup = $categories
            ->mapWithKeys(fn (FinancialCategory $category) => [Str::lower($category->name) => $category->id]);
        $costCenterLookup = $costCenters
            ->mapWithKeys(fn (FinancialCostCenter $center) => [Str::lower($center->name) => $center->id]);
        $contracts = Contract::query()
            ->orderByDesc('id')
            ->limit(500)
            ->get([
                'id',
                'code',
                'title',
                'client_id',
                'condominium_id',
                'unit_id',
                'process_id',
                'type',
                'billing_type',
                'monthly_value',
                'total_value',
                'installment_quantity',
                'due_day',
                'recurrence',
                'payment_method',
                'financial_account_id',
                'responsible_user_id',
                'cost_center_future',
                'financial_category_future',
                'financial_notes',
                'start_date',
                'end_date',
                'indefinite_term',
                'status',
                'generate_financial_entries',
            ]);
        $contractSnapshots = $contracts->mapWithKeys(function (Contract $contract) use ($categoryLookup, $costCenterLookup) {
            $normalizedBillingType = Str::of(Str::ascii((string) $contract->billing_type))->lower()->squish()->toString();
            $receivableBillingType = match ($normalizedBillingType) {
                'mensal' => 'mensalidade',
                'parcelada' => 'parcela',
                'unica' => in_array(Str::of(Str::ascii((string) $contract->type))->lower()->squish()->toString(), [
                    Str::of(Str::ascii('Termo de acordo'))->lower()->squish()->toString(),
                    Str::of(Str::ascii('Confissao de divida'))->lower()->squish()->toString(),
                ], true) ? 'parcela' : 'honorario',
                default => null,
            };
            $estimatedAmount = match ($normalizedBillingType) {
                'mensal' => (float) ($contract->monthly_value ?: 0),
                'parcelada' => ($contract->installment_quantity ?: 0) > 0
                    ? round((float) ($contract->total_value ?: 0) / max(1, (int) $contract->installment_quantity), 2)
                    : (float) ($contract->total_value ?: 0),
                'unica' => (float) ($contract->total_value ?: 0),
                default => 0.0,
            };

            return [
                $contract->id => [
                    'id' => $contract->id,
                    'client_id' => $contract->client_id,
                    'condominium_id' => $contract->condominium_id,
                    'unit_id' => $contract->unit_id,
                    'process_id' => $contract->process_id,
                    'billing_type' => $contract->billing_type,
                    'receivable_billing_type' => $receivableBillingType,
                    'monthly_value' => (float) ($contract->monthly_value ?: 0),
                    'total_value' => (float) ($contract->total_value ?: 0),
                    'estimated_amount' => $estimatedAmount,
                    'installment_quantity' => $contract->installment_quantity,
                    'due_day' => $contract->due_day,
                    'recurrence' => $contract->recurrence,
                    'payment_method' => $contract->payment_method,
                    'account_id' => $contract->financial_account_id,
                    'responsible_user_id' => $contract->responsible_user_id,
                    'category_id' => $contract->financial_category_future
                        ? ($categoryLookup[Str::lower(trim((string) $contract->financial_category_future))] ?? null)
                        : null,
                    'cost_center_id' => $contract->cost_center_future
                        ? ($costCenterLookup[Str::lower(trim((string) $contract->cost_center_future))] ?? null)
                        : null,
                    'financial_notes' => $contract->financial_notes,
                    'start_date' => optional($contract->start_date)->format('Y-m-d'),
                    'end_date' => optional($contract->end_date)->format('Y-m-d'),
                    'indefinite_term' => (bool) $contract->indefinite_term,
                    'status' => $contract->status,
                    'generate_financial_entries' => (bool) $contract->generate_financial_entries,
                ],
            ];
        });

        return [
            'clients' => ClientEntity::query()->where('is_active', 1)->orderBy('display_name')->get(['id', 'display_name', 'cpf_cnpj']),
            'condominiums' => ClientCondominium::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'units' => ClientUnit::query()->with(['condominium', 'block'])->orderBy('unit_number')->get(),
            'contracts' => $contracts,
            'contractSnapshots' => $contractSnapshots,
            'processes' => class_exists(ProcessCase::class)
                ? ProcessCase::query()->orderByDesc('id')->limit(500)->get(['id', 'process_number', 'client_name_snapshot'])
                : collect(),
            'users' => User::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'categories' => $categories,
            'costCenters' => $costCenters,
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
