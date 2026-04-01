<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

class AncoraMail
{
    public static function applySmtpSettings(): bool
    {
        $smtp = AncoraSettings::smtp();

        if (empty($smtp['host']) || empty($smtp['from_address'])) {
            return false;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $smtp['host']);
        Config::set('mail.mailers.smtp.port', (int) ($smtp['port'] ?: 587));
        Config::set('mail.mailers.smtp.encryption', $smtp['encryption'] ?: null);
        Config::set('mail.mailers.smtp.username', $smtp['username'] ?: null);
        Config::set('mail.mailers.smtp.password', $smtp['password'] ?: null);
        Config::set('mail.from.address', $smtp['from_address']);
        Config::set('mail.from.name', $smtp['from_name'] ?: 'Âncora');

        return true;
    }
}
