<?php

namespace App\Support;

use App\Models\FinancialSetting;
use App\Support\Financeiro\FinancialCatalog;

class FinancialSettings
{
    public static function all(): array
    {
        return FinancialSetting::query()->pluck('value', 'key')->all();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        static $settings = null;

        if ($settings === null) {
            $settings = static::all();
        }

        return array_key_exists($key, $settings)
            ? $settings[$key]
            : ($default ?? FinancialCatalog::settings()[$key] ?? null);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return static::get($key, $default ? '1' : '0') === '1';
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) (static::get($key, (string) $default) ?? $default);
    }

    public static function defaults(): array
    {
        return FinancialCatalog::settings();
    }
}
