<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FinancialReceivableSeriesService
{
    public function buildManualRows(
        array $payload,
        ?string $recurrence,
        ?int $occurrences = null,
        ?Carbon $repeatUntil = null
    ): array {
        $normalizedRecurrence = $this->normalizeRecurrence($recurrence);
        $dueDate = $this->resolveDate($payload['due_date'] ?? null) ?? now()->startOfDay();
        $competenceDate = $this->resolveDate($payload['competence_date'] ?? null)
            ?? $this->defaultCompetenceDate($dueDate, $normalizedRecurrence);

        $rows = [];
        $cursorDueDate = $dueDate->copy();
        $cursorCompetenceDate = $competenceDate->copy();
        $maxOccurrences = max(1, min((int) ($occurrences ?: 1), 240));
        $lastDueDate = $repeatUntil?->copy()->endOfDay();
        $index = 0;

        while (true) {
            if ($lastDueDate && $cursorDueDate->gt($lastDueDate)) {
                break;
            }

            if (!$lastDueDate && $index >= $maxOccurrences) {
                break;
            }

            $rows[] = $this->manualRow(
                $payload,
                $normalizedRecurrence,
                $cursorDueDate->copy(),
                $cursorCompetenceDate->copy()
            );

            $index++;

            if ($normalizedRecurrence === null || $normalizedRecurrence === 'unica') {
                break;
            }

            if ($lastDueDate === null && $index >= $maxOccurrences) {
                break;
            }

            [$cursorDueDate, $cursorCompetenceDate] = $this->advanceManualCursor(
                $cursorDueDate,
                $cursorCompetenceDate,
                $normalizedRecurrence
            );
        }

        return $this->annotateSeries($rows);
    }

    public function buildContractRows(Contract $contract): array
    {
        $billingType = (string) $contract->billing_type;
        $dueDay = max(1, min(31, (int) ($contract->due_day ?: 10)));

        $rows = match ($billingType) {
            'mensal' => $this->buildMonthlyContractRows($contract, $dueDay),
            'parcelada' => $this->buildInstallmentContractRows($contract, $dueDay),
            'unica' => $this->buildSingleContractRows($contract, $dueDay),
            default => [],
        };

        return $this->annotateSeries($rows);
    }

    public function buildContractRowsInRange(Contract $contract, Carbon $from, Carbon $to): array
    {
        return array_values(array_filter(
            $this->buildContractRows($contract),
            fn (array $row) => $row['due_date']->betweenIncluded($from, $to)
        ));
    }

    public function makeSeriesGroup(?string $prefix = null): string
    {
        $base = trim((string) $prefix);

        return ($base !== '' ? $base . '-' : '') . (string) Str::uuid();
    }

    private function manualRow(array $payload, ?string $recurrence, Carbon $dueDate, Carbon $competenceDate): array
    {
        $label = $this->manualLabel($dueDate, $recurrence);
        $title = trim((string) ($payload['title'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));

        return [
            'title' => $title,
            'reference' => $reference,
            'billing_type' => $payload['billing_type'] ?? null,
            'amount' => round((float) ($payload['original_amount'] ?? 0), 2),
            'due_date' => $dueDate,
            'competence_date' => $competenceDate,
            'recurrence' => $recurrence,
            'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
            'series_label' => $label,
        ];
    }

    private function buildMonthlyContractRows(Contract $contract, int $dueDay): array
    {
        $amount = round((float) ($contract->monthly_value ?: 0), 2);
        if ($amount <= 0) {
            return [];
        }

        $recurrence = $this->normalizeRecurrence((string) ($contract->recurrence ?: 'mensal')) ?? 'mensal';
        $monthsStep = $this->recurrenceMonths($recurrence);
        $start = $contract->start_date?->copy()->startOfDay() ?: now()->startOfDay();
        $firstDueDate = $this->firstDueDate($start, $dueDay);
        $end = $contract->indefinite_term || !$contract->end_date
            ? max($firstDueDate->copy(), now()->copy()->startOfDay())->addMonthsNoOverflow(11)->endOfMonth()
            : $contract->end_date->copy()->endOfDay();

        $rows = [];
        $cursor = $firstDueDate->copy();

        while ($cursor->lte($end)) {
            if ($contract->indefinite_term && $cursor->lt(now()->startOfDay())) {
                $cursor->addMonthsNoOverflow($monthsStep);
                continue;
            }

            $rows[] = [
                'title' => $contract->title . ' - ' . $this->competenceLabel($cursor),
                'reference' => $this->competenceLabel($cursor),
                'billing_type' => 'mensalidade',
                'amount' => $amount,
                'due_date' => $cursor->copy(),
                'competence_date' => $cursor->copy()->startOfMonth(),
                'recurrence' => $recurrence,
                'notes' => 'Gerado automaticamente a partir do contrato ' . ($contract->code ?: ('#' . $contract->id)) . '.',
            ];

            $cursor->addMonthsNoOverflow($monthsStep);
        }

        return $rows;
    }

    private function buildInstallmentContractRows(Contract $contract, int $dueDay): array
    {
        $count = max(0, (int) ($contract->installment_quantity ?: 0));
        $total = round((float) ($contract->total_value ?: 0), 2);
        if ($count < 1 || $total <= 0) {
            return [];
        }

        $start = $contract->start_date?->copy()->startOfDay() ?: now()->startOfDay();
        $firstDueDate = $this->firstDueDate($start, $dueDay);
        $amounts = $this->splitAmount($total, $count);
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $dueDate = $firstDueDate->copy()->addMonthsNoOverflow($index);
            $number = $index + 1;
            $rows[] = [
                'title' => $contract->title . ' - Parcela ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $count, 2, '0', STR_PAD_LEFT),
                'reference' => 'Parcela ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $count, 2, '0', STR_PAD_LEFT),
                'billing_type' => 'parcela',
                'amount' => $amounts[$index],
                'due_date' => $dueDate,
                'competence_date' => $dueDate->copy()->startOfMonth(),
                'installment_number' => $number,
                'installment_total' => $count,
                'recurrence' => $count > 1 ? 'mensal' : 'unica',
                'notes' => 'Gerado automaticamente a partir do contrato ' . ($contract->code ?: ('#' . $contract->id)) . '.',
            ];
        }

        return $rows;
    }

    private function buildSingleContractRows(Contract $contract, int $dueDay): array
    {
        $amount = round((float) ($contract->total_value ?: 0), 2);
        if ($amount <= 0) {
            return [];
        }

        $start = $contract->start_date?->copy()->startOfDay() ?: now()->startOfDay();
        $dueDate = $this->firstDueDate($start, $dueDay);

        return [[
            'title' => $contract->title,
            'reference' => 'Parcela unica',
            'billing_type' => $this->singleBillingType($contract),
            'amount' => $amount,
            'due_date' => $dueDate,
            'competence_date' => $dueDate->copy()->startOfMonth(),
            'recurrence' => 'unica',
            'notes' => 'Gerado automaticamente a partir do contrato ' . ($contract->code ?: ('#' . $contract->id)) . '.',
        ]];
    }

    private function annotateSeries(array $rows): array
    {
        $total = count($rows);
        if ($total === 0) {
            return [];
        }

        foreach ($rows as $index => &$row) {
            $row['series_index'] = $total > 1 ? $index + 1 : 1;
            $row['series_total'] = $total;

            if ($total > 1 && !empty($row['series_label'])) {
                $baseTitle = trim((string) ($row['title'] ?? ''));
                $reference = trim((string) ($row['reference'] ?? ''));

                $row['title'] = $baseTitle !== '' ? $baseTitle . ' - ' . $row['series_label'] : $row['series_label'];
                $row['reference'] = $reference !== '' ? $reference : $row['series_label'];
            }
        }
        unset($row);

        return $rows;
    }

    private function resolveDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->startOfDay();
    }

    private function defaultCompetenceDate(Carbon $dueDate, ?string $recurrence): Carbon
    {
        return in_array($recurrence, ['semanal', 'quinzenal'], true)
            ? $dueDate->copy()
            : $dueDate->copy()->startOfMonth();
    }

    private function advanceManualCursor(Carbon $dueDate, Carbon $competenceDate, string $recurrence): array
    {
        return match ($recurrence) {
            'semanal' => [$dueDate->copy()->addWeek(), $competenceDate->copy()->addWeek()],
            'quinzenal' => [$dueDate->copy()->addDays(15), $competenceDate->copy()->addDays(15)],
            'bimestral' => [$dueDate->copy()->addMonthsNoOverflow(2), $competenceDate->copy()->addMonthsNoOverflow(2)->startOfMonth()],
            'trimestral' => [$dueDate->copy()->addMonthsNoOverflow(3), $competenceDate->copy()->addMonthsNoOverflow(3)->startOfMonth()],
            'semestral' => [$dueDate->copy()->addMonthsNoOverflow(6), $competenceDate->copy()->addMonthsNoOverflow(6)->startOfMonth()],
            'anual' => [$dueDate->copy()->addYearNoOverflow(), $competenceDate->copy()->addYearNoOverflow()->startOfMonth()],
            default => [$dueDate->copy()->addMonthNoOverflow(), $competenceDate->copy()->addMonthNoOverflow()->startOfMonth()],
        };
    }

    private function manualLabel(Carbon $dueDate, ?string $recurrence): string
    {
        return in_array($recurrence, ['mensal', 'bimestral', 'trimestral', 'semestral', 'anual'], true)
            ? $this->competenceLabel($dueDate)
            : $dueDate->format('d/m/Y');
    }

    private function normalizeRecurrence(?string $recurrence): ?string
    {
        $value = Str::of(Str::ascii(trim((string) $recurrence)))->lower()->squish()->toString();

        return $value !== '' ? $value : null;
    }

    private function splitAmount(float $total, int $count): array
    {
        $totalCents = (int) round($total * 100);
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;
        $amounts = [];

        for ($index = 0; $index < $count; $index++) {
            $cents = $base + ($index < $remainder ? 1 : 0);
            $amounts[] = round($cents / 100, 2);
        }

        return $amounts;
    }

    private function recurrenceMonths(string $recurrence): int
    {
        return match ($recurrence) {
            'bimestral' => 2,
            'trimestral' => 3,
            'semestral' => 6,
            'anual' => 12,
            default => 1,
        };
    }

    private function firstDueDate(Carbon $start, int $dueDay): Carbon
    {
        $candidate = $start->copy()->day(min($dueDay, $start->daysInMonth));
        if ($candidate->lt($start)) {
            $nextMonth = $start->copy()->addMonthNoOverflow()->startOfMonth();

            return $nextMonth->copy()->day(min($dueDay, $nextMonth->daysInMonth));
        }

        return $candidate;
    }

    private function singleBillingType(Contract $contract): string
    {
        $type = Str::of(Str::ascii((string) $contract->type))->lower()->squish()->toString();

        return in_array($type, [
            Str::of(Str::ascii('Termo de acordo'))->lower()->squish()->toString(),
            Str::of(Str::ascii('Confissao de divida'))->lower()->squish()->toString(),
        ], true) ? 'parcela' : 'honorario';
    }

    private function competenceLabel(Carbon $date): string
    {
        return $date->copy()->startOfMonth()->translatedFormat('m/Y');
    }
}
