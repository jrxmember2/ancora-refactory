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
        $appName = self::get('app_name', 'Âncora') ?: 'Âncora';
        $company = self::get('app_company', $appName) ?: $appName;
        $logoLight = self::get('branding_logo_light_path', '/branding/logo-light.svg') ?: '/branding/logo-light.svg';
        $logoDark = self::get('branding_logo_dark_path', '/branding/logo-dark.svg') ?: '/branding/logo-dark.svg';
        $premiumVariant = self::get('branding_premium_logo_variant', 'light') === 'dark' ? 'dark' : 'light';

        return [
            'app_name' => $appName,
            'company_name' => $company,
            'base_url' => self::get('app_base_url', config('app.url')),
            'logo_light' => $logoLight,
            'logo_dark' => $logoDark,
            'logo_premium' => $premiumVariant === 'dark' ? $logoDark : $logoLight,
            'premium_logo_variant' => $premiumVariant,
            'favicon' => self::get('branding_favicon_path', '/favicon.ico') ?: '/favicon.ico',
            'company_phone' => self::get('company_phone', ''),
            'company_email' => self::get('company_email', ''),
            'company_address' => self::get('company_address', ''),
            'company_website' => self::get('company_website', 'www.serratech.tec.br') ?: 'www.serratech.tec.br',
            'company_social_primary' => self::get('company_social_primary', '@serratech.br') ?: '@serratech.br',
            'company_social_secondary' => self::get('company_social_secondary', '@serratech.br') ?: '@serratech.br',
            'logo_height_desktop' => (int) (self::get('branding_logo_height_desktop', '44') ?: 44),
            'logo_height_mobile' => (int) (self::get('branding_logo_height_mobile', '36') ?: 36),
            'logo_height_login' => (int) (self::get('branding_logo_height_login', '82') ?: 82),
        ];
    }
}
