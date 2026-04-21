<?php

namespace App\Support\Automation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class AutomationText
{
    private function __construct()
    {
    }

    public static function normalize(?string $value): string
    {
        return Str::of(Str::ascii((string) $value))
            ->lower()
            ->squish()
            ->toString();
    }

    public static function normalizeCompact(?string $value): string
    {
        return str_replace(' ', '', self::normalize($value));
    }

    public static function normalizeUnit(?string $value): string
    {
        return Str::of((string) $value)
            ->upper()
            ->squish()
            ->replace(' ', '')
            ->toString();
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function parseOption(?string $value, int $max): ?int
    {
        $digits = self::digits($value);
        if ($digits === '') {
            return null;
        }

        $number = (int) $digits;

        return $number >= 1 && $number <= $max ? $number : null;
    }

    public static function parseYesNo(?string $value): ?bool
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'sim', 's', 'claro', 'confirmo', 'positivo', 'ok', 'isso'], true)) {
            return true;
        }

        if (in_array($normalized, ['2', 'nao', 'n', 'negativo'], true)) {
            return false;
        }

        return null;
    }

    public static function firstName(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return Str::of($value)->before(' ')->toString();
    }

    public static function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function similarity(?string $left, ?string $right): float
    {
        $left = self::normalizeCompact($left);
        $right = self::normalizeCompact($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return (float) $percent;
    }
}
