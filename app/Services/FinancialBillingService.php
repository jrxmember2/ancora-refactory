<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\FinancialReceivable;
use App\Support\Financeiro\FinancialCatalog;
use App\Support\Financeiro\FinancialValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialBillingService
{
    public function __construct(
        private readonly FinancialCodeService $codeService,
        private readonly FinancialLedgerService $ledgerService,
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
        $contract->loadMissing(['client', 'condominium', 'unit', 'responsible']);

        $dueDay = max(1, min(31, (int) ($contract->due_day ?: 10)));
        $amount = (float) ($contract->monthly_value ?: $contract->contract_value ?: $contract->total_value ?: 0);
        $billingType = $contract->billing_type ?: 'mensalidade';

        if ($amount <= 0) {
            throw new \RuntimeException('O contrato nao possui valor configurado para faturamento.');
        }

        $dates = $this->scheduledDates($contract, $from, $to, $dueDay);
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($contract, $dates, $amount, $billingType, $userId, &$created, &$skipped) {
            foreach ($dates as $dueDate) {
                $exists = FinancialReceivable::query()
                    ->where('contract_id', $contract->id)
                    ->whereDate('due_date', $dueDate->toDateString())
                    ->where('billing_type', $billingType)
                    ->exists();

                if ($exists) {
                    $skipped[] = $dueDate->toDateString();
                    continue;
                }

                $receivable = FinancialReceivable::query()->create([
                    'code' => $this->codeService->next('financial_receivables', 'entry_prefix', 'REC'),
                    'title' => $contract->title . ' - ' . FinancialValue::competenceLabel($dueDate->copy()->startOfMonth()),
                    'reference' => FinancialValue::competenceLabel($dueDate->copy()->startOfMonth()),
                    'billing_type' => $billingType,
                    'client_id' => $contract->client_id,
                    'condominium_id' => $contract->condominium_id,
                    'unit_id' => $contract->unit_id,
                    'contract_id' => $contract->id,
                    'process_id' => $contract->process_id,
                    'category_id' => $this->defaultRevenueCategoryId($billingType),
                    'account_id' => $contract->financial_account_id,
                    'original_amount' => round($amount, 2),
                    'final_amount' => round($amount, 2),
                    'due_date' => $dueDate->toDateString(),
                    'competence_date' => $dueDate->copy()->startOfMonth()->toDateString(),
                    'payment_method' => $contract->payment_method,
                    'status' => 'aberto',
                    'generate_collection' => true,
                    'responsible_user_id' => $contract->responsible_user_id,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'notes' => 'Gerado automaticamente a partir do contrato ' . ($contract->code ?: ('#' . $contract->id)) . '.',
                ]);

                $this->ledgerService->syncReceivable($receivable);
                $created[] = $receivable;
            }
        });

        return [
            'created' => collect($created),
            'skipped' => $skipped,
        ];
    }

    private function scheduledDates(Contract $contract, Carbon $from, Carbon $to, int $dueDay): array
    {
        $start = $from->copy()->startOfMonth();
        $end = $to->copy()->endOfMonth();
        $dates = [];

        if (($contract->recurrence ?: 'mensal') === 'unica') {
            $date = $contract->start_date ? $contract->start_date->copy() : $from->copy();
            $date = $date->day(min($dueDay, $date->daysInMonth));
            if ($date->betweenIncluded($from, $to)) {
                $dates[] = $date;
            }

            return $dates;
        }

        $cursor = $start->copy();
        while ($cursor <= $end) {
            $candidate = $cursor->copy()->day(min($dueDay, $cursor->daysInMonth));
            if ($contract->start_date && $candidate->lt($contract->start_date)) {
                $cursor->addMonth();
                continue;
            }
            if ($contract->end_date && !$contract->indefinite_term && $candidate->gt($contract->end_date)) {
                $cursor->addMonth();
                continue;
            }
            if ($candidate->betweenIncluded($from, $to)) {
                $dates[] = $candidate;
            }
            $cursor->addMonth();
        }

        return $dates;
    }

    private function defaultRevenueCategoryId(string $billingType): ?int
    {
        $map = [
            'mensalidade' => 'Assessoria',
            'honorario' => 'Honorarios',
            'exito' => 'Honorarios',
            'parcela' => 'Acordos',
            'cobranca_condominial' => 'Cobranca',
            'reembolso' => 'Reembolso',
            'receita_extraordinaria' => 'Acordos',
        ];

        $name = $map[$billingType] ?? 'Honorarios';
        return \App\Models\FinancialCategory::query()->where('name', $name)->value('id');
    }
}
