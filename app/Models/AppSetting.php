<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
