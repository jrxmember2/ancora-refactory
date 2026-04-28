<?php

namespace App\Support;

use App\Models\ContractSetting;
use App\Support\Contracts\ContractCatalog;

class ContractSettings
{
    public static function all(): array
    {
        return ContractSetting::query()->pluck('value', 'key')->all();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        static $settings = null;

        if ($settings === null) {
            $settings = static::all();
        }

        return array_key_exists($key, $settings) ? $settings[$key] : ($default ?? ContractCatalog::defaultSettings()[$key] ?? null);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return static::get($key, $default ? '1' : '0') === '1';
    }

    public static function jsonArray(string $key): array
    {
        $value = static::get($key, '[]');
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function defaults(): array
    {
        return ContractCatalog::defaultSettings();
    }
}
