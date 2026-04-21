<?php

namespace App\Services;

use App\Models\CobrancaCase;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaMonetaryIndexFactor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class CobrancaMonetaryUpdateService
{
    /** @var array<string, float> */
    private array $factorCache = [];

    public function calculate(CobrancaCase $case, array $options): array
    {
        $case->loadMissing('quotas');

        $settings = $this->normalizeOptions($options);
        $quotas = $this->selectedQuotas($case->quotas, $settings['quota_ids']);
        if ($quotas->isEmpty()) {
            throw new RuntimeException('Selecione ao menos uma quota para atualizar.');
        }

        $items = [];
        $totals = [
            'original_cents' => 0,
            'corrected_cents' => 0,
            'interest_cents' => 0,
            'fine_cents' => 0,
            'items_total_cents' => 0,
            'quota_count' => 0,
        ];

        foreach ($quotas as $quota) {
            $item = $this->calculateQuota($quota, $settings);
            $items[] = $item;
            $totals['original_cents'] += $item['original_cents'];
            $totals['corrected_cents'] += $item['corrected_cents'];
            $totals['interest_cents'] += $item['interest_cents'];
            $totals['fine_cents'] += $item['fine_cents'];
            $totals['items_total_cents'] += $item['total_cents'];
            $totals['quota_count']++;
        }

        $costsCorrectedCents = $this->correctCosts($settings);
        $debitTotalCents = max(0, $totals['items_total_cents'] + $costsCorrectedCents - $settings['abatement_cents']);
        $attorneyFeeCents = $this->attorneyFeeCents($debitTotalCents, $settings);
        $boletoFeeCents = $settings['apply_boleto_fee']
            ? ((int) $totals['quota_count'] * (int) $settings['boleto_fee_cents'])
            : 0;
        $boletoCancellationFeeCents = $settings['apply_boleto_cancellation_fee']
            ? ((int) $totals['quota_count'] * (int) $settings['boleto_cancellation_fee_cents'])
            : 0;
        $grandTotalCents = $debitTotalCents + $attorneyFeeCents + $boletoFeeCents + $boletoCancellationFeeCents;

        $totals += [
            'costs_cents' => $settings['costs_cents'],
            'costs_corrected_cents' => $costsCorrectedCents,
            'boleto_fee_cents' => $boletoFeeCents,
            'boleto_cancellation_fee_cents' => $boletoCancellationFeeCents,
            'abatement_cents' => $settings['abatement_cents'],
            'debit_total_cents' => $debitTotalCents,
            'attorney_fee_cents' => $attorneyFeeCents,
            'grand_total_cents' => $grandTotalCents,
        ];

        return [
            'settings' => $settings,
            'items' => $items,
            'totals' => $totals,
            'summary' => $this->summary($totals, $settings),
        ];
    }

    public function formatPreview(array $calculation): array
    {
        return [
            'settings' => [
                'index_label' => $calculation['settings']['index_label'],
                'final_date' => $calculation['settings']['final_date']->format('d/m/Y'),
                'interest_label' => $calculation['settings']['interest_label'],
                'attorney_fee_label' => $calculation['settings']['attorney_fee_label'],
            ],
            'items' => collect($calculation['items'])->map(fn (array $item) => [
                'quota_id' => $item['quota_id'],
                'reference_label' => $item['reference_label'] ?: $item['due_date']->format('m/Y'),
                'due_date' => $item['due_date']->format('d/m/Y'),
                'original' => $this->money($item['original_cents']),
                'factor' => number_format($item['correction_factor'], 10, ',', '.'),
                'corrected' => $this->money($item['corrected_cents']),
                'interest_percent' => number_format($item['interest_percent'], 2, ',', '.') . '%',
                'interest' => $this->money($item['interest_cents']),
                'fine' => $this->money($item['fine_cents']),
                'total' => $this->money($item['total_cents']),
            ])->values()->all(),
            'totals' => [
                'original' => $this->money($calculation['totals']['original_cents']),
                'corrected' => $this->money($calculation['totals']['corrected_cents']),
                'interest' => $this->money($calculation['totals']['interest_cents']),
                'fine' => $this->money($calculation['totals']['fine_cents']),
                'costs_corrected' => $this->money($calculation['totals']['costs_corrected_cents']),
                'boleto_fee' => $this->money($calculation['totals']['boleto_fee_cents']),
                'boleto_cancellation_fee' => $this->money($calculation['totals']['boleto_cancellation_fee_cents']),
                'abatement' => $this->money($calculation['totals']['abatement_cents']),
                'debit_total' => $this->money($calculation['totals']['debit_total_cents']),
                'attorney_fee' => $this->money($calculation['totals']['attorney_fee_cents']),
                'grand_total' => $this->money($calculation['totals']['grand_total_cents']),
            ],
            'summary' => $calculation['summary'],
        ];
    }

    private function normalizeOptions(array $options): array
    {
        $finalDate = $this->date($options['final_date'] ?? null) ?: now()->endOfMonth();
        $indexCode = strtoupper(trim((string) ($options['index_code'] ?? 'ATM'))) ?: 'ATM';
        $interestType = trim((string) ($options['interest_type'] ?? 'legal')) ?: 'legal';
        if (!in_array($interestType, ['legal', 'contractual', 'none'], true)) {
            $interestType = 'legal';
        }

        $attorneyFeeType = trim((string) ($options['attorney_fee_type'] ?? 'percent')) ?: 'percent';
        if (!in_array($attorneyFeeType, ['percent', 'fixed', 'none'], true)) {
            $attorneyFeeType = 'percent';
        }

        $interestRateMonthly = $this->percentValue($options['interest_rate_monthly'] ?? 0);
        if ($interestType === 'contractual' && $interestRateMonthly <= 0) {
            throw new RuntimeException('Informe o percentual mensal dos juros contratuais.');
        }

        $attorneyFeeValue = $attorneyFeeType === 'fixed'
            ? $this->moneyToCents($options['attorney_fee_value'] ?? 0)
            : $this->percentValue($options['attorney_fee_value'] ?? 0);

        return [
            'index_code' => $indexCode,
            'index_label' => $indexCode === 'ATM' ? 'Indice do TJES' : $indexCode,
            'calculation_date' => now()->toDateString(),
            'final_date' => $finalDate->copy()->startOfDay(),
            'interest_type' => $interestType,
            'interest_label' => match ($interestType) {
                'contractual' => 'Juros contratuais de ' . number_format($interestRateMonthly, 2, ',', '.') . '% ao mes',
                'none' => 'Sem juros moratorios',
                default => 'Juros legais do Codigo Civil',
            },
            'interest_rate_monthly' => $interestRateMonthly,
            'fine_percent' => $this->percentValue($options['fine_percent'] ?? 0),
            'attorney_fee_type' => $attorneyFeeType,
            'attorney_fee_label' => match ($attorneyFeeType) {
                'fixed' => 'Honorarios fixos',
                'none' => 'Sem honorarios',
                default => 'Honorarios percentuais',
            },
            'attorney_fee_value' => $attorneyFeeValue,
            'costs_cents' => $this->moneyToCents($options['costs_amount'] ?? 0),
            'costs_date' => $this->date($options['costs_date'] ?? null),
            'boleto_fee_cents' => $this->moneyToCents($options['boleto_fee_amount'] ?? 0),
            'boleto_cancellation_fee_cents' => $this->moneyToCents($options['boleto_cancellation_fee_amount'] ?? 0),
            'apply_boleto_fee' => filter_var($options['apply_boleto_fee'] ?? false, FILTER_VALIDATE_BOOL),
            'apply_boleto_cancellation_fee' => filter_var($options['apply_boleto_cancellation_fee'] ?? false, FILTER_VALIDATE_BOOL),
            'abatement_cents' => $this->moneyToCents($options['abatement_amount'] ?? 0),
            'quota_ids' => collect((array) ($options['quota_ids'] ?? []))->map(fn ($id) => (int) $id)->filter()->values()->all(),
        ];
    }

    private function selectedQuotas(Collection $quotas, array $quotaIds): Collection
    {
        return $quotas
            ->when($quotaIds !== [], fn (Collection $items) => $items->whereIn('id', $quotaIds))
            ->filter(fn (CobrancaCaseQuota $quota) => $this->moneyToCents($quota->original_amount) > 0)
            ->sortBy('due_date')
            ->values();
    }

    private function calculateQuota(CobrancaCaseQuota $quota, array $settings): array
    {
        $dueDate = Carbon::parse($quota->due_date)->startOfDay();
        $finalDate = $settings['final_date'];
        $originalCents = $this->moneyToCents($quota->original_amount);
        $correctionFactor = $dueDate->gt($finalDate)
            ? 1.0
            : $this->correctionFactor($settings['index_code'], $dueDate, $finalDate);
        $correctedCents = (int) round($originalCents * $correctionFactor);
        $interestMonths = $dueDate->gt($finalDate) ? 0.0 : $this->months30($dueDate, $finalDate);
        $interestPercent = $this->interestPercent($dueDate, $finalDate, $settings);
        $interestCents = (int) round($correctedCents * ($interestPercent / 100));
        $fineCents = (int) round($correctedCents * ($settings['fine_percent'] / 100));
        $totalCents = $correctedCents + $interestCents + $fineCents;

        return [
            'quota_id' => $quota->id,
            'reference_label' => (string) $quota->reference_label,
            'due_date' => $dueDate,
            'original_cents' => $originalCents,
            'correction_factor' => $correctionFactor,
            'corrected_cents' => $correctedCents,
            'interest_months' => $interestMonths,
            'interest_percent' => $interestPercent,
            'interest_cents' => $interestCents,
            'fine_percent' => $settings['fine_percent'],
            'fine_cents' => $fineCents,
            'total_cents' => $totalCents,
        ];
    }

    private function correctCosts(array $settings): int
    {
        if ($settings['costs_cents'] <= 0) {
            return 0;
        }

        if (!$settings['costs_date'] || $settings['costs_date']->gt($settings['final_date'])) {
            return $settings['costs_cents'];
        }

        return (int) round($settings['costs_cents'] * $this->correctionFactor(
            $settings['index_code'],
            $settings['costs_date'],
            $settings['final_date']
        ));
    }

    private function attorneyFeeCents(int $debitTotalCents, array $settings): int
    {
        return match ($settings['attorney_fee_type']) {
            'fixed' => max(0, (int) $settings['attorney_fee_value']),
            'none' => 0,
            default => (int) round($debitTotalCents * (((float) $settings['attorney_fee_value']) / 100)),
        };
    }

    private function correctionFactor(string $indexCode, Carbon $startDate, Carbon $finalDate): float
    {
        $startFactor = $this->factorFor($indexCode, (int) $startDate->year, (int) $startDate->month);
        $finalFactor = $this->factorFor($indexCode, (int) $finalDate->year, (int) $finalDate->month);
        if ($finalFactor <= 0) {
            throw new RuntimeException('Fator final invalido para o indice ' . $indexCode . '.');
        }

        return $startFactor / $finalFactor;
    }

    private function factorFor(string $indexCode, int $year, int $month): float
    {
        $key = $indexCode . ':' . $year . ':' . $month;
        if (array_key_exists($key, $this->factorCache)) {
            return $this->factorCache[$key];
        }

        $factor = CobrancaMonetaryIndexFactor::query()
            ->where('index_code', $indexCode)
            ->where('year', $year)
            ->where('month', $month)
            ->value('factor');

        if ($factor === null) {
            throw new RuntimeException("Nao ha fator TJES cadastrado para {$month}/{$year}.");
        }

        return $this->factorCache[$key] = (float) $factor;
    }

    private function interestPercent(Carbon $startDate, Carbon $finalDate, array $settings): float
    {
        if ($settings['interest_type'] === 'none' || $startDate->gt($finalDate)) {
            return 0.0;
        }

        if ($settings['interest_type'] === 'contractual') {
            return round($this->months30($startDate, $finalDate) * (float) $settings['interest_rate_monthly'], 4);
        }

        $changeDate = Carbon::create(2003, 1, 11)->startOfDay();
        if ($finalDate->lt($changeDate)) {
            return round($this->months30($startDate, $finalDate) * 0.5, 4);
        }
        if ($startDate->gte($changeDate)) {
            return round($this->months30($startDate, $finalDate), 4);
        }

        $before = $this->months30($startDate, $changeDate->copy()->subDay()) * 0.5;
        $after = $this->months30($changeDate, $finalDate);

        return round($before + $after, 4);
    }

    private function months30(Carbon $startDate, Carbon $finalDate): float
    {
        if ($finalDate->lte($startDate)) {
            return 0.0;
        }

        $months = (($finalDate->year - $startDate->year) * 12) + ($finalDate->month - $startDate->month);
        $days = $finalDate->day - $startDate->day;

        return max(0.0, round($months + ($days / 30), 6));
    }

    private function summary(array $totals, array $settings): array
    {
        return [
            'debit_total' => $this->money($totals['debit_total_cents']),
            'attorney_fee' => $this->money($totals['attorney_fee_cents']),
            'boleto_fee' => $this->money($totals['boleto_fee_cents']),
            'boleto_cancellation_fee' => $this->money($totals['boleto_cancellation_fee_cents']),
            'grand_total' => $this->money($totals['grand_total_cents']),
            'final_date' => $settings['final_date']->format('d/m/Y'),
        ];
    }

    private function date(mixed $value): ?Carbon
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function percentValue(mixed $value): float
    {
        $raw = preg_replace('/[^\d,.-]/', '', (string) ($value ?? '')) ?: '0';
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? round(max(0, (float) $raw), 4) : 0.0;
    }

    private function moneyToCents(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) round(((float) $value) * 100);
        }

        $raw = preg_replace('/[^\d,.-]/', '', (string) ($value ?? '')) ?: '';
        if ($raw === '') {
            return 0;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? (int) round(((float) $raw) * 100) : 0;
    }

    private function money(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }
}
