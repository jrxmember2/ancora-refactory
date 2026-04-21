<?php

namespace App\Services\Automation;

use App\Models\AutomationAgreementProposal;
use App\Models\AutomationSession;
use App\Models\CobrancaMonetaryUpdate;
use App\Services\CobrancaMonetaryUpdateService;
use Illuminate\Support\Carbon;
use RuntimeException;

class AutomationAgreementService
{
    public function __construct(private readonly CobrancaMonetaryUpdateService $monetaryUpdateService)
    {
    }

    public function normalizePaymentMode(?string $messageText): ?string
    {
        $normalized = trim((string) $messageText);
        $yesNo = null;

        if ($normalized === '1' || preg_match('/avista|à vista|a vista/i', $normalized)) {
            return 'cash';
        }

        if ($normalized === '2' || preg_match('/parcelad|parcela/i', $normalized)) {
            return 'installments';
        }

        return $yesNo;
    }

    public function normalizeInstallments(?string $messageText): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $messageText) ?? '';
        if ($digits === '') {
            return null;
        }

        return (int) $digits;
    }

    public function isInstallmentCountValid(int $installments): bool
    {
        $min = (int) config('automation.collection.installments.min', 2);
        $max = (int) config('automation.collection.installments.max', 12);

        return $installments >= $min && $installments <= $max;
    }

    public function maximumFirstDueDate(?Carbon $reference = null): Carbon
    {
        $reference = ($reference ?: now())->copy()->startOfDay();
        $daysLimit = (int) config('automation.collection.first_due_date.days_from_today_limit', 15);
        $monthCutoffDays = (int) config('automation.collection.first_due_date.month_end_cutoff_days', 2);

        $byDays = $reference->copy()->addDays($daysLimit);
        $byMonthEnd = $reference->copy()->endOfMonth()->subDays($monthCutoffDays);

        return $byDays->lessThan($byMonthEnd) ? $byDays : $byMonthEnd;
    }

    public function validateFirstDueDate(Carbon $date, ?Carbon $reference = null): ?string
    {
        $reference = ($reference ?: now())->copy()->startOfDay();
        $limit = $this->maximumFirstDueDate($reference);

        if ($date->lt($reference)) {
            return 'A data do primeiro pagamento não pode ser anterior a hoje.';
        }

        if ($date->gt($limit)) {
            return 'A data informada ultrapassa o limite permitido. Escolha uma data até ' . $limit->format('d/m/Y') . '.';
        }

        return null;
    }

    public function createProposal(AutomationSession $session, string $paymentMode, ?int $installments, Carbon $firstDueDate): AutomationAgreementProposal
    {
        $session->loadMissing(['condominium', 'unit', 'cobrancaCase.monetaryUpdates']);

        $case = $session->cobrancaCase;
        $condominium = $session->condominium;
        if (!$case || !$condominium) {
            throw new RuntimeException('A sessão não possui uma OS de cobrança válida para calcular o acordo.');
        }

        $calculation = $this->monetaryUpdateService->calculate($case->loadMissing('quotas'), [
            'final_date' => $firstDueDate->toDateString(),
            'index_code' => (string) config('automation.collection.monetary.index_code', 'ATM'),
            'interest_type' => (string) config('automation.collection.monetary.interest_type', 'legal'),
            'fine_percent' => (float) config('automation.collection.monetary.fine_percent', 2),
            'attorney_fee_type' => 'percent',
            'attorney_fee_value' => $case->charge_type === 'judicial'
                ? (float) config('automation.collection.attorney_fees.judicial_percent', 20)
                : (float) config('automation.collection.attorney_fees.extrajudicial_percent', 10),
            'costs_amount' => $case->charge_type === 'judicial' ? $this->judicialCostsAmount($case) : 0,
            'costs_date' => $case->charge_type === 'judicial' ? $this->judicialCostsDate($case) : null,
            'boleto_fee_amount' => $condominium->boleto_fee_amount,
            'boleto_cancellation_fee_amount' => $condominium->boleto_cancellation_fee_amount,
            'apply_boleto_fee' => (bool) config('automation.collection.boleto_fees.enabled', true),
            'apply_boleto_cancellation_fee' => (bool) config('automation.collection.boleto_fees.cancellation_enabled', true),
        ]);

        $proposal = AutomationAgreementProposal::query()->create([
            'session_id' => $session->id,
            'payment_mode' => $paymentMode,
            'installments' => $paymentMode === 'installments' ? $installments : null,
            'first_due_date' => $firstDueDate->toDateString(),
            'base_total' => round(((int) data_get($calculation, 'totals.original_cents', 0)) / 100, 2),
            'updated_total' => round(((int) data_get($calculation, 'totals.grand_total_cents', 0)) / 100, 2),
            'calculation_memory' => $this->calculationMemory($calculation, $paymentMode, $installments),
            'rules_snapshot' => [
                'payment_mode' => $paymentMode,
                'installments' => $paymentMode === 'installments' ? $installments : null,
                'first_due_date_limit' => $this->maximumFirstDueDate()->toDateString(),
            ],
            'status' => 'accepted',
        ]);

        return $proposal->refresh();
    }

    private function judicialCostsAmount($case): float
    {
        $latest = $case->monetaryUpdates
            ->sortByDesc('created_at')
            ->first(fn (CobrancaMonetaryUpdate $update) => (float) $update->costs_amount > 0);

        return (float) ($latest?->costs_amount ?? 0);
    }

    private function judicialCostsDate($case): ?string
    {
        $latest = $case->monetaryUpdates
            ->sortByDesc('created_at')
            ->first(fn (CobrancaMonetaryUpdate $update) => (float) $update->costs_amount > 0);

        return $latest?->costs_date?->toDateString();
    }

    private function calculationMemory(array $calculation, string $paymentMode, ?int $installments): array
    {
        $totals = $calculation['totals'] ?? [];
        $grandTotalCents = (int) ($totals['grand_total_cents'] ?? 0);
        $perInstallmentCents = $paymentMode === 'installments' && $installments
            ? (int) round($grandTotalCents / $installments)
            : $grandTotalCents;

        return [
            'components' => [
                'principal' => $this->centsToDecimal((int) ($totals['original_cents'] ?? 0)),
                'tjes_update' => $this->centsToDecimal((int) (($totals['corrected_cents'] ?? 0) - ($totals['original_cents'] ?? 0))),
                'interest' => $this->centsToDecimal((int) ($totals['interest_cents'] ?? 0)),
                'fine' => $this->centsToDecimal((int) ($totals['fine_cents'] ?? 0)),
                'process_costs' => $this->centsToDecimal((int) ($totals['costs_corrected_cents'] ?? 0)),
                'attorney_fees' => $this->centsToDecimal((int) ($totals['attorney_fee_cents'] ?? 0)),
                'boleto_fee_total' => $this->centsToDecimal((int) ($totals['boleto_fee_cents'] ?? 0)),
                'boleto_cancellation_fee_total' => $this->centsToDecimal((int) ($totals['boleto_cancellation_fee_cents'] ?? 0)),
                'total_final' => $this->centsToDecimal($grandTotalCents),
            ],
            'per_installment_amount' => $this->centsToDecimal($perInstallmentCents),
            'service_summary' => $calculation['summary'] ?? [],
        ];
    }

    private function centsToDecimal(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
