<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Models\ClientEntity;
use App\Support\AncoraAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Helpers compartilhados de atualizacao monetaria/PDF/contato extraidos do CobrancaController
 * durante a decomposicao (strangler). Usado pelos controllers de Cobranca.
 *
 * Nota de decomposicao: estes metodos ainda existem em CobrancaController (duplicados de
 * proposito para nao alterar os fluxos de producao naquele arquivo). Quando o CobrancaController
 * for decomposto, ele deve passar a usar este trait e remover as copias privadas.
 */
trait CobrancaMonetarySupport
{
    protected function renderPdfWithChromium(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable([
            'chromium',
            'chromium-browser',
            'google-chrome',
            'google-chrome-stable',
        ]);
        if (!$binary) {
            return false;
        }

        $profileDir = dirname($pdfPath) . DIRECTORY_SEPARATOR . pathinfo($pdfPath, PATHINFO_FILENAME) . '-chrome-profile';
        File::ensureDirectoryExists($profileDir);

        try {
            $process = new Process([
                $binary,
                '--headless',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-extensions',
                '--no-first-run',
                '--no-default-browser-check',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--print-to-pdf-no-header',
                '--user-data-dir=' . $profileDir,
                '--print-to-pdf=' . $pdfPath,
                'file://' . str_replace('\\', '/', $htmlPath),
            ], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        } finally {
            File::deleteDirectory($profileDir);
        }
    }

    protected function renderPdfWithWkhtmltopdf(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable(['wkhtmltopdf']);
        if (!$binary) {
            return false;
        }

        try {
            $process = new Process([
                $binary,
                '--enable-local-file-access',
                '--encoding',
                'UTF-8',
                '--page-size',
                'A4',
                '--margin-top',
                '0',
                '--margin-right',
                '0',
                '--margin-bottom',
                '0',
                '--margin-left',
                '0',
                $htmlPath,
                $pdfPath,
            ], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function availableExecutable(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            try {
                $process = new Process([$candidate, '--version'], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // Some utilities do not expose --version consistently; fall back to PATH lookup below.
            }

            try {
                $process = new Process(['sh', '-lc', 'command -v ' . escapeshellarg($candidate)], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function decimalFromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    protected function moneyToDb(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = preg_replace('/[^\d,.-]/', '', (string) $value) ?: '';
        if ($raw === '') {
            return null;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);
        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }

    protected function monetaryPayload(array $calculation): array
    {
        $settings = $calculation['settings'];

        return [
            'settings' => [
                'index_code' => $settings['index_code'],
                'index_label' => $settings['index_label'],
                'calculation_date' => $settings['calculation_date'],
                'final_date' => $settings['final_date']->toDateString(),
                'interest_type' => $settings['interest_type'],
                'interest_rate_monthly' => $settings['interest_rate_monthly'],
                'fine_percent' => $settings['fine_percent'],
                'attorney_fee_type' => $settings['attorney_fee_type'],
                'attorney_fee_value' => $settings['attorney_fee_value'],
                'costs_cents' => $settings['costs_cents'],
                'costs_date' => $settings['costs_date']?->toDateString(),
                'boleto_fee_cents' => $settings['boleto_fee_cents'],
                'boleto_cancellation_fee_cents' => $settings['boleto_cancellation_fee_cents'],
                'apply_boleto_fee' => $settings['apply_boleto_fee'],
                'apply_boleto_cancellation_fee' => $settings['apply_boleto_cancellation_fee'],
                'abatement_cents' => $settings['abatement_cents'],
                'quota_ids' => $settings['quota_ids'],
            ],
            'totals_cents' => $calculation['totals'],
            'summary' => $calculation['summary'],
        ];
    }

    protected function firstEntityEmail(?ClientEntity $entity): ?string
    {
        $email = data_get(collect($entity?->emails_json ?? [])->first(), 'email');
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    protected function primaryEntityPhone(?ClientEntity $entity): ?string
    {
        return collect($this->entityPhones($entity, 30))->first();
    }

    protected function entityPhones(?ClientEntity $entity, int $maxLength = 40): array
    {
        return collect($entity?->phones_json ?? [])
            ->flatMap(function ($row) use ($maxLength) {
                $value = is_array($row) ? ($row['number'] ?? null) : (is_scalar($row) ? (string) $row : null);
                return $this->extractPhoneValues($value, $maxLength);
            })
            ->filter(fn (?string $value) => $value !== null && $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected function extractPhoneValues(mixed $value, int $maxLength = 40): array
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return [];
        }

        preg_match_all('/(?:\+?55[\s.\-]*)?(?:\(?\d{2}\)?[\s.\-]*)?\d{4,5}(?:[\s.\-]?\d{4})/', $raw, $matches);
        $values = collect($matches[0] ?? [])
            ->map(fn ($match) => $this->normalizePhoneValue($match, $maxLength))
            ->filter(fn (?string $phone) => $phone !== null && $phone !== '')
            ->unique()
            ->values()
            ->all();

        if ($values !== []) {
            return $values;
        }

        $singleValue = $this->normalizePhoneValue($raw, $maxLength);
        return $singleValue ? [$singleValue] : [];
    }

    protected function normalizePhoneValue(mixed $value, int $maxLength = 40): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/(?:\+?55[\s.\-]*)?(?:\(?\d{2}\)?[\s.\-]*)?\d{4,5}(?:[\s.\-]?\d{4})/', $raw, $matches) === 1) {
            $raw = trim((string) ($matches[0] ?? $raw));
        }

        $digits = $this->digitsOnly($raw);
        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            $formatted = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
            return Str::limit($formatted, $maxLength, '');
        }

        if (strlen($digits) === 10) {
            $formatted = preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
            return Str::limit($formatted, $maxLength, '');
        }

        $normalized = preg_replace('/\s+/', ' ', $raw) ?: $raw;
        return Str::limit($normalized, $maxLength, '');
    }

    protected function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    protected function excelSerialDateToIso(string $value): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d{2,6}(?:\.\d+)?$/', $value)) {
            return null;
        }

        $serial = (float) $value;
        if ($serial < 25569 || $serial > 60000) {
            return null;
        }

        try {
            return (new \DateTimeImmutable('1899-12-30'))
                ->modify('+' . (int) floor($serial) . ' days')
                ->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeReferenceLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $serialDate = $this->excelSerialDateToIso($value);
        if ($serialDate !== null) {
            return (new \DateTimeImmutable($serialDate))->format('m/Y');
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return $m[2] . '/' . $m[1];
        }
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . '/' . $m[2];
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) === 6) {
            $first = substr($digits, 0, 2);
            if ((int) $first >= 1 && (int) $first <= 12) {
                return $first . '/' . substr($digits, 2, 4);
            }
            return substr($digits, 4, 2) . '/' . substr($digits, 0, 4);
        }

        return $value;
    }

    protected function logAction(Request $request, string $action, int $entityId, string $details): void
    {
        $user = AncoraAuth::user($request);
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'user_email' => $user?->email ?? 'desconhecido',
            'action' => $action,
            'entity_type' => 'cobrancas',
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
        $request->attributes->set('audit.skip_generic', true);
    }
}
