<?php

namespace App\Services;

use App\Support\FinancialSettings;
use Illuminate\Support\Facades\DB;

class FinancialCodeService
{
    public function next(string $table, string $prefixKey, string $fallbackPrefix): string
    {
        $prefix = trim((string) FinancialSettings::get($prefixKey, $fallbackPrefix));
        $prefix = $prefix !== '' ? strtoupper($prefix) : strtoupper($fallbackPrefix);
        $datePart = now()->format('Ym');
        $base = $prefix . '-' . $datePart . '-';

        $latest = DB::table($table)
            ->where('code', 'like', $base . '%')
            ->orderByDesc('id')
            ->value('code');

        $nextNumber = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
