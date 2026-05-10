<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    use HasFactory;

    protected $table = 'app_settings';

    protected $fillable = ['setting_key', 'setting_value', 'description'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::query()->where('setting_key', $key)->value('setting_value') ?? $default;
    }

    public static function setValue(string $key, ?string $value, ?string $description = null): void
    {
        static::query()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value, 'description' => $description]
        );
    }

    public static function hasValue(string $key): bool
    {
        $value = static::getValue($key);

        return trim((string) $value) !== '';
    }

    public static function getDecryptedValue(string $key, ?string $default = null): ?string
    {
        $value = static::getValue($key);
        if (trim((string) $value) === '') {
            return $default;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function setEncryptedValue(string $key, ?string $value, ?string $description = null): void
    {
        $normalized = trim((string) $value);

        static::setValue(
            $key,
            $normalized === '' ? '' : Crypt::encryptString($normalized),
            $description
        );
    }
}
