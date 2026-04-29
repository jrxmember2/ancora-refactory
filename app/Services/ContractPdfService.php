<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ContractPdfService
{
    public function __construct(
        private readonly ContractRenderService $renderService,
    ) {
    }

    public function generate(Contract $contract, ?int $userId = null, ?string $notes = null, array $appendixAttachments = []): ContractVersion
    {
        $contract->loadMissing(['template', 'client', 'condominium.syndic', 'unit.block', 'responsible']);

        $nextVersion = ((int) $contract->versions()->max('version_number')) + 1;
        $baseDir = storage_path('app/public/contracts/' . $contract->id . '/versions');
        File::ensureDirectoryExists($baseDir);

        $slug = Str::slug($contract->code ?: $contract->title ?: 'contrato');
        $htmlPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $nextVersion, 2, '0', STR_PAD_LEFT) . '.html';
        $pdfFilename = $slug . '-v' . str_pad((string) $nextVersion, 2, '0', STR_PAD_LEFT) . '.pdf';
        $pdfPath = $baseDir . DIRECTORY_SEPARATOR . $pdfFilename;
        $relativePdfPath = 'contracts/' . $contract->id . '/versions/' . $pdfFilename;

        $payload = $this->renderService->documentPayload($contract);
        $appendixSections = $this->buildAppendixSections($appendixAttachments);
        File::put($htmlPath, view('pages.contratos.document', array_merge($payload, [
            'pdfMode' => true,
            'autoPrint' => false,
            'appendixSections' => $appendixSections,
        ]))->render());

        $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
            || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath, $contract->template?->margins_json ?? null);

        File::delete($htmlPath);

        if (!$generated || !is_file($pdfPath)) {
            File::delete($pdfPath);
            throw new \RuntimeException('Nao foi possivel gerar o PDF do contrato neste ambiente.');
        }

        $version = ContractVersion::query()->create([
            'contract_id' => $contract->id,
            'version_number' => $nextVersion,
            'content_html' => (string) $contract->content_html,
            'pdf_path' => $relativePdfPath,
            'notes' => $notes,
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);

        $contract->forceFill([
            'final_pdf_path' => $relativePdfPath,
            'final_pdf_generated_at' => now(),
        ])->save();

        return $version;
    }

    public function absolutePath(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return null;
        }

        $path = storage_path('app/public/' . ltrim($relativePath, '/'));
        return is_file($path) ? $path : null;
    }

    private function renderPdfWithChromium(string $htmlPath, string $pdfPath): bool
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

    private function renderPdfWithWkhtmltopdf(string $htmlPath, string $pdfPath, ?array $margins = null): bool
    {
        $binary = $this->availableExecutable(['wkhtmltopdf']);
        if (!$binary) {
            return false;
        }

        $margins = array_merge(['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10], $margins ?? []);

        try {
            $process = new Process([
                $binary,
                '--enable-local-file-access',
                '--encoding',
                'UTF-8',
                '--page-size',
                'A4',
                '--margin-top',
                (string) $margins['top'],
                '--margin-right',
                (string) $margins['right'],
                '--margin-bottom',
                (string) $margins['bottom'],
                '--margin-left',
                (string) $margins['left'],
                $htmlPath,
                $pdfPath,
            ], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        }
    }

    private function availableExecutable(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            try {
                $process = new Process([$candidate, '--version'], timeout: 15);
                $process->run();
                if ($process->isSuccessful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // Ignora e tenta o proximo binario.
            }

            try {
                $where = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN' ? 'where' : 'which';
                $process = new Process([$where, $candidate], timeout: 10);
                $process->run();
                if ($process->isSuccessful()) {
                    $path = trim((string) $process->getOutput());
                    if ($path !== '') {
                        return preg_split('/\r\n|\r|\n/', $path)[0];
                    }
                }
            } catch (\Throwable) {
                // Segue para o proximo candidato.
            }
        }

        return null;
    }

    private function buildAppendixSections(array $attachments): array
    {
        return collect($attachments)
            ->map(function ($attachment) {
                if (!is_array($attachment)) {
                    return null;
                }

                $path = $this->resolveAppendixPath($attachment);
                if (!$path) {
                    return null;
                }

                $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                $pages = match ($extension) {
                    'pdf' => $this->pdfPagesToDataUris($path),
                    'png', 'jpg', 'jpeg', 'webp' => array_filter([$this->imageToDataUri($path)]),
                    default => [],
                };

                if ($pages === []) {
                    return null;
                }

                return [
                    'id' => $attachment['id'] ?? null,
                    'original_name' => $attachment['original_name'] ?? basename($path),
                    'owner_label' => $attachment['owner_label'] ?? '',
                    'pages' => array_map(fn (string $src) => ['src' => $src], $pages),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveAppendixPath(array $attachment): ?string
    {
        $relativePath = trim((string) ($attachment['relative_path'] ?? ''));
        if ($relativePath === '') {
            return null;
        }

        $publicPath = public_path(ltrim($relativePath, '/'));
        if (is_file($publicPath)) {
            return $publicPath;
        }

        $storagePath = storage_path('app/public/' . ltrim($relativePath, '/'));

        return is_file($storagePath) ? $storagePath : null;
    }

    private function pdfPagesToDataUris(string $pdfPath): array
    {
        $binary = $this->availableExecutable(['pdftoppm']);
        if (!$binary) {
            return [];
        }

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ancora-contract-appendix-' . Str::random(10);
        File::ensureDirectoryExists($tempDir);
        $outputPrefix = $tempDir . DIRECTORY_SEPARATOR . 'page';

        try {
            $process = new Process([
                $binary,
                '-png',
                $pdfPath,
                $outputPrefix,
            ], timeout: 120);
            $process->run();

            if (!$process->isSuccessful()) {
                return [];
            }

            return collect(File::files($tempDir))
                ->filter(fn ($file) => strtolower((string) $file->getExtension()) === 'png')
                ->sortBy(fn ($file) => $file->getFilename())
                ->map(fn ($file) => $this->imageToDataUri($file->getPathname()))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    private function imageToDataUri(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };

        if (!$mime) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
