<?php

namespace App\Support;

use NumberFormatter;

class BrazilianCurrencyFormatter
{
    public static function toWords(float|int|string|null $amount): string
    {
        $amount = round((float) ($amount ?? 0), 2);
        $negative = $amount < 0;
        $amount = abs($amount);

        $reais = (int) floor($amount);
        $cents = (int) round(($amount - $reais) * 100);

        $formatter = class_exists(NumberFormatter::class)
            ? new NumberFormatter('pt_BR', NumberFormatter::SPELLOUT)
            : null;

        $realWords = self::spell($formatter, $reais);
        $centWords = self::spell($formatter, $cents);

        $parts = [];
        if ($reais > 0) {
            $parts[] = $realWords . ' ' . ($reais === 1 ? 'real' : 'reais');
        }

        if ($cents > 0) {
            $parts[] = $centWords . ' ' . ($cents === 1 ? 'centavo' : 'centavos');
        }

        if ($parts === []) {
            $parts[] = 'zero real';
        }

        $result = implode(' e ', $parts);

        return $negative ? 'menos ' . $result : $result;
    }

    private static function spell(?NumberFormatter $formatter, int $value): string
    {
        if ($formatter instanceof NumberFormatter) {
            $text = trim((string) $formatter->format($value));
            if ($text !== '') {
                return $text;
            }
        }

        return (string) $value;
    }
}
