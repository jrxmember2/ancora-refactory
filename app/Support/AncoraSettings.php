<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;

class AncoraSettings
{
    private static function assetUrlOrFallback(?string $path, string $fallback): string
    {
        $candidate = trim((string) $path);
        if ($candidate === '') {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $candidate)) {
            return $candidate;
        }

        $relative = '/' . ltrim($candidate, '/');
        $absolute = public_path(ltrim($relative, '/'));

        return is_file($absolute) ? $relative : $fallback;
    }

    public static function all(): array
    {
        return AppSetting::query()->pluck('setting_value', 'setting_key')->all();
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return AppSetting::getValue($key, $default);
    }

    public static function getJson(string $key, array $default = []): array
    {
        $value = static::get($key);
        if (!$value) {
            return $default;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public static function brand(): array
    {
        $appName = self::get('app_name', 'Âncora') ?: 'Âncora';
        $company = self::get('app_company', $appName) ?: $appName;
        $logoLight = self::assetUrlOrFallback(self::get('branding_logo_light_path', '/imgs/logomarca.svg'), '/imgs/logomarca.svg');
        $logoDark = self::assetUrlOrFallback(self::get('branding_logo_dark_path', '/imgs/logomarca.svg'), '/imgs/logomarca.svg');
        $premiumVariant = self::get('branding_premium_logo_variant', 'light') === 'dark' ? 'dark' : 'light';

        return [
            'app_name' => $appName,
            'company_name' => $company,
            'slogan' => self::get('app_slogan', 'Plataforma modular para gestão jurídica, comercial e condominial.') ?: '',
            'base_url' => self::get('app_base_url', config('app.url')),
            'logo_light' => $logoLight,
            'logo_dark' => $logoDark,
            'logo_premium' => $premiumVariant === 'dark' ? $logoDark : $logoLight,
            'premium_logo_variant' => $premiumVariant,
            'favicon' => self::assetUrlOrFallback(self::get('branding_favicon_path', '/favicon.ico'), '/favicon.svg'),
            'company_phone' => self::get('company_phone', ''),
            'company_email' => self::get('company_email', ''),
            'company_address' => self::get('company_address', ''),
            'company_website' => self::get('company_website', 'https://serratech.tec.br') ?: 'https://serratech.tec.br',
            'powered_by_name' => self::get('powered_by_name', 'Serratech Soluções em TI') ?: 'Serratech Soluções em TI',
            'powered_by_url' => self::get('powered_by_url', 'https://serratech.tec.br') ?: 'https://serratech.tec.br',
            'logo_height_desktop' => (int) (self::get('branding_logo_height_desktop', '44') ?: 44),
            'logo_height_mobile' => (int) (self::get('branding_logo_height_mobile', '36') ?: 36),
            'logo_height_login' => (int) (self::get('branding_logo_height_login', '82') ?: 82),
        ];
    }

    public static function smtp(): array
    {
        return [
            'host' => self::get('smtp_host', ''),
            'port' => self::get('smtp_port', '587'),
            'username' => self::get('smtp_username', ''),
            'password' => self::get('smtp_password', ''),
            'encryption' => self::get('smtp_encryption', 'tls') ?: 'tls',
            'from_address' => self::get('smtp_from_address', self::get('company_email', '')), 
            'from_name' => self::get('smtp_from_name', self::get('app_name', 'Âncora')),
        ];
    }

    public static function billingSmtp(): array
    {
        return [
            'host' => self::get('billing_smtp_host', ''),
            'port' => self::get('billing_smtp_port', '587'),
            'username' => self::get('billing_smtp_username', ''),
            'password' => self::get('billing_smtp_password', ''),
            'encryption' => self::get('billing_smtp_encryption', 'tls') ?: 'tls',
            'from_address' => self::get('billing_smtp_from_address', self::get('company_email', '')),
            'from_name' => self::get('billing_smtp_from_name', 'Ã‚ncora CobranÃ§a'),
        ];
    }

    public static function billingImap(): array
    {
        return [
            'host' => self::get('billing_imap_host', ''),
            'port' => self::get('billing_imap_port', '993'),
            'username' => self::get('billing_imap_username', ''),
            'password' => self::get('billing_imap_password', ''),
            'encryption' => self::get('billing_imap_encryption', 'ssl') ?: 'ssl',
            'sent_folder' => self::get('billing_imap_sent_folder', 'Sent'),
            'validate_cert' => self::get('billing_imap_validate_cert', '0') === '1',
        ];
    }

    public static function systemAlert(): array
    {
        $title = trim((string) self::get('system_alert_title', ''));
        $message = trim((string) self::get('system_alert_message', ''));
        $level = trim((string) self::get('system_alert_level', 'warning'));
        $visibleUntilRaw = trim((string) self::get('system_alert_visible_until', ''));
        $visibleUntil = null;

        if ($visibleUntilRaw !== '') {
            try {
                $visibleUntil = Carbon::parse($visibleUntilRaw);
            } catch (\Throwable) {
                $visibleUntil = null;
            }
        }

        $enabled = self::get('system_alert_enabled', '0') === '1'
            && ($title !== '' || $message !== '')
            && (!$visibleUntil || $visibleUntil->isFuture());

        return [
            'is_active' => $enabled,
            'title' => $title,
            'message' => $message,
            'level' => in_array($level, ['info', 'warning', 'error', 'success'], true) ? $level : 'warning',
            'visible_until' => $visibleUntil,
            'visible_until_input' => $visibleUntil?->format('Y-m-d\TH:i'),
        ];
    }
}
