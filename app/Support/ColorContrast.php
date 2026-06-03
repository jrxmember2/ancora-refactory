<?php

namespace App\Support;

class ColorContrast
{
    /**
     * Normaliza uma cor hexadecimal para o formato #rrggbb minusculo, ou null se invalida.
     * Aceita #rgb, #rrggbb (com ou sem #).
     */
    public static function normalizeHex(?string $value): ?string
    {
        $hex = strtolower(trim((string) $value));
        $hex = ltrim($hex, '#');

        if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (!preg_match('/^[0-9a-f]{6}$/', $hex)) {
            return null;
        }

        return '#' . $hex;
    }

    /**
     * Retorna a cor de texto (#111827 escuro ou #ffffff claro) que melhor contrasta
     * com o fundo informado, usando a luminancia relativa (WCAG).
     */
    public static function idealTextColor(?string $background, string $dark = '#111827', string $light = '#ffffff'): string
    {
        $hex = self::normalizeHex($background);
        if ($hex === null) {
            return $dark;
        }

        $r = hexdec(substr($hex, 1, 2)) / 255;
        $g = hexdec(substr($hex, 3, 2)) / 255;
        $b = hexdec(substr($hex, 5, 2)) / 255;

        $channel = static function (float $c): float {
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return $luminance > 0.5 ? $dark : $light;
    }
}
