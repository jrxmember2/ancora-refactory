<?php

namespace App\Services;

use App\Models\FinancialCashFlow;
use App\Models\FinancialPayable;
use App\Models\FinancialReceivable;
use App\Models\FinancialTransaction;
use App\Support\Financeiro\FinancialValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialLedgerService
{
    public function __construct(
        private readonly FinancialCodeService $codeService,
    ) {
    }

    public function recordReceivableSettlement(
        FinancialReceivable $receivable,
        float $amount,
        ?Carbon $date,
        ?int $accountId,
        ?string $paymentMethod,
        ?string $description,
        ?int $userId
    ): FinancialTransaction {
        return DB::transaction(function () use ($receivable, $amount, $date, $accountId, $paymentMethod, $description, $userId) {
            $transaction = FinancialTransaction::query()->create([
                'code' => $this->codeService->next('financial_transactions', 'entry_prefix', 'MOV'),
                'transaction_type' => 'entrada',
                'account_id' => $accountId,
                'category_id' => $receivable->category_id,
                'cost_center_id' => $receivable->cost_center_id,
                'receivable_id' => $receivable->id,
                'contract_id' => $receivable->contract_id,
                'amount' => $amount,
                'transaction_date' => ($date ?? now())->toDateTimeString(),
                'source' => 'Conta a receber',
                'payment_method' => $paymentMethod,
                'description' => $description ?: ('Baixa de ' . ($receivable->code ?: ('recebivel #' . $receivable->id))),
                'created_by' => $userId,
            ]);

            $this->mirrorCashFlow($transaction, 'real', 'entrada');
            $this->syncReceivable($receivable->fresh());

            return $transaction;
        });
    }

    public function recordPayableSettlement(
        FinancialPayable $payable,
        float $amount,
        ?Carbon $date,
        ?int $accountId,
        ?string $paymentMethod,
        ?string $description,
        ?int $userId
    ): FinancialTransaction {
        return DB::transaction(function () use ($payable, $amount, $date, $accountId, $paymentMethod, $description, $userId) {
            $transaction = FinancialTransaction::query()->create([
                'code' => $this->codeService->next('financial_transactions', 'entry_prefix', 'MOV'),
                'transaction_type' => 'saida',
                'account_id' => $accountId,
                'category_id' => $payable->category_id,
                'cost_center_id' => $payable->cost_center_id,
                'payable_id' => $payable->id,
                'amount' => $amount,
                'transaction_date' => ($date ?? now())->toDateTimeString(),
                'source' => 'Conta a pagar',
                'payment_method' => $paymentMethod,
                'description' => $description ?: ('Baixa de ' . ($payable->code ?: ('pagavel #' . $payable->id))),
                'created_by' => $userId,
            ]);

            $this->mirrorCashFlow($transaction, 'real', 'saida');
            $this->syncPayable($payable->fresh());

            return $transaction;
        });
    }

    public function recordStandaloneTransaction(array $payload): FinancialTransaction
    {
        return DB::transaction(function () use ($payload) {
            $transaction = FinancialTransaction::query()->create([
                'code' => $payload['code'] ?? $this->codeService->next('financial_transactions', 'entry_prefix', 'MOV'),
                'transaction_type' => $payload['transaction_type'],
                'account_id' => $payload['account_id'] ?? null,
                'destination_account_id' => $payload['destination_account_id'] ?? null,
                'category_id' => $payload['category_id'] ?? null,
                'cost_center_id' => $payload['cost_center_id'] ?? null,
                'receivable_id' => $payload['receivable_id'] ?? null,
                'payable_id' => $payload['payable_id'] ?? null,
                'reimbursement_id' => $payload['reimbursement_id'] ?? null,
                'process_cost_id' => $payload['process_cost_id'] ?? null,
                'installment_id' => $payload['installment_id'] ?? null,
                'contract_id' => $payload['contract_id'] ?? null,
                'amount' => $payload['amount'] ?? 0,
                'transaction_date' => ($payload['transaction_date'] ?? now()),
                'source' => $payload['source'] ?? null,
                'document_number' => $payload['document_number'] ?? null,
                'payment_method' => $payload['payment_method'] ?? null,
                'description' => $payload['description'] ?? null,
                'metadata_json' => $payload['metadata_json'] ?? null,
                'created_by' => $payload['created_by'] ?? null,
            ]);

            $direction = $payload['direction']
                ?? (($payload['transaction_type'] ?? 'entrada') === 'entrada' ? 'entrada' : 'saida');

            $this->mirrorCashFlow($transaction, $payload['kind'] ?? 'real', $direction);

            if ($transaction->receivable_id) {
                $this->syncReceivable($transaction->receivable()->first());
            }
            if ($transaction->payable_id) {
                $this->syncPayable($transaction->payable()->first());
            }

            return $transaction;
        });
    }

    public function syncReceivable(?FinancialReceivable $receivable): void
    {
        if (!$receivable) {
            return;
        }

        $receivedAmount = (float) $receivable->transactions()->sum('amount');
        $finalAmount = $this->receivableFinalAmount($receivable);
        $status = $receivable->status;

        if ($receivedAmount <= 0.0) {
            $status = $receivable->due_date && $receivable->due_date->isPast() ? 'vencido' : 'aberto';
        } elseif ($receivedAmount < $finalAmount) {
            $status = 'parcial';
        } else {
            $status = 'recebido';
        }

        $receivable->forceFill([
            'received_amount' => round($receivedAmount, 2),
            'final_amount' => round($finalAmount, 2),
            'status' => $status,
            'received_at' => $receivedAmount > 0 ? ($receivable->transactions()->max('transaction_date') ?? now()) : null,
        ])->save();
    }

    public function syncPayable(?FinancialPayable $payable): void
    {
        if (!$payable) {
            return;
        }

        $paidAmount = (float) $payable->transactions()->sum('amount');
        $status = $payable->status;

        if ($paidAmount <= 0.0) {
            $status = $payable->due_date && $payable->due_date->isPast() ? 'vencido' : 'aberto';
        } elseif ($paidAmount < (float) $payable->amount) {
            $status = 'parcial';
        } else {
            $status = 'pago';
        }

        $payable->forceFill([
            'paid_amount' => round($paidAmount, 2),
            'status' => $status,
            'paid_at' => $paidAmount > 0 ? ($payable->transactions()->max('transaction_date') ?? now()) : null,
        ])->save();
    }

    public function receivableFinalAmount(FinancialReceivable $receivable): float
    {
        $computed = (float) $receivable->original_amount
            + (float) $receivable->interest_amount
            + (float) $receivable->penalty_amount
            + (float) $receivable->correction_amount
            - (float) $receivable->discount_amount;

        return round($computed, 2);
    }

    private function mirrorCashFlow(FinancialTransaction $transaction, string $kind, string $direction): void
    {
        FinancialCashFlow::query()->updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'account_id' => $transaction->account_id,
                'receivable_id' => $transaction->receivable_id,
                'payable_id' => $transaction->payable_id,
                'kind' => $kind,
                'direction' => $direction,
                'amount' => $transaction->amount,
                'movement_date' => $transaction->transaction_date,
                'status' => $transaction->reconciliation_status,
                'source_label' => $transaction->source,
                'description' => $transaction->description,
            ]
        );
    }
}
