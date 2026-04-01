<?php

namespace App\Support;

use App\Models\AppSetting;

class AncoraSettings
{
    public static function all(): array
    {
        return AppSetting::query()->pluck('setting_value', 'setting_key')->all();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return AppSetting::getValue($key, $default);
    }

    public static function brand(): array
    {
        return [
            'app_name' => self::get('app_company', 'Âncora'),
            'base_url' => self::get('app_base_url', config('app.url')),
            'logo_light' => self::get('branding_logo_light_path', '/branding/logo-light.svg'),
            'logo_dark' => self::get('branding_logo_dark_path', '/branding/logo-dark.svg'),
            'favicon' => self::get('branding_favicon_path', '/favicon.ico'),
            'company_phone' => self::get('company_phone', ''),
            'company_email' => self::get('company_email', ''),
            'company_address' => self::get('company_address', ''),
        ];
    }
}
