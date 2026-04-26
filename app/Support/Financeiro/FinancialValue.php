<?php

namespace App\Support\Financeiro;

use Illuminate\Support\Carbon;

class FinancialValue
{
    public static function decimalFromInput(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $normalized = str_replace(['.', ','], ['', '.'], $text);
        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    public static function dateFromInput(mixed $value): ?Carbon
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            return Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function money(float|int|string|null $value): string
    {
        return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }

    public static function competenceLabel(?Carbon $date): string
    {
        return $date ? $date->format('m/Y') : '-';
    }
}
