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
        $generated = false;
        $mpdfHtmlPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $nextVersion, 2, '0', STR_PAD_LEFT) . '-mpdf.html';
        $externalFooterHtmlPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $nextVersion, 2, '0', STR_PAD_LEFT) . '-external-footer.html';
        $mpdfFooterHtmlPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $nextVersion, 2, '0', STR_PAD_LEFT) . '-mpdf-footer.html';
        $wkhtmlFooterHtmlPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $nextVersion, 2, '0', STR_PAD_LEFT) . '-wkhtml-footer.html';
        $chromiumFooterHtmlPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $nextVersion, 2, '0', STR_PAD_LEFT) . '-chromium-footer.html';

        File::put($mpdfHtmlPath, view('pages.contratos.document', array_merge($payload, [
            'pdfMode' => true,
            'autoPrint' => false,
            'appendixSections' => [],
            'renderFooterInBody' => false,
            'pdfEngine' => 'mpdf',
        ]))->render());
        File::put($externalFooterHtmlPath, view('pages.contratos.document', array_merge($payload, [
            'pdfMode' => true,
            'autoPrint' => false,
            'appendixSections' => [],
            'renderFooterInBody' => false,
            'pdfEngine' => 'browser',
        ]))->render());
        File::put($mpdfFooterHtmlPath, $this->buildMpdfFooterTemplate($payload));
        File::put($wkhtmlFooterHtmlPath, $this->buildWkhtmlFooterHtml($payload));
        File::put($chromiumFooterHtmlPath, $this->buildChromiumFooterHtml($payload));

        $generated = $this->renderPdfWithMpdf(
            $mpdfHtmlPath,
            $pdfPath,
            $contract->template?->margins_json ?? null,
            (string) ($contract->template?->page_size ?? 'a4'),
            (string) ($contract->template?->page_orientation ?? 'portrait'),
            $mpdfFooterHtmlPath
        );

        if (!$generated) {
            $generated = $this->renderPdfWithChromiumDevtools(
                $externalFooterHtmlPath,
                $pdfPath,
                $contract->template?->margins_json ?? null,
                (string) ($contract->template?->page_size ?? 'a4'),
                (string) ($contract->template?->page_orientation ?? 'portrait'),
                $chromiumFooterHtmlPath
            );
        }

        if (!$generated) {
            $generated = $this->renderPdfWithWkhtmltopdf(
                $externalFooterHtmlPath,
                $pdfPath,
                $contract->template?->margins_json ?? null,
                (string) ($contract->template?->page_size ?? 'a4'),
                (string) ($contract->template?->page_orientation ?? 'portrait'),
                $wkhtmlFooterHtmlPath
            );
        }

        File::delete([$mpdfHtmlPath, $externalFooterHtmlPath, $mpdfFooterHtmlPath, $wkhtmlFooterHtmlPath, $chromiumFooterHtmlPath]);

        if (!$generated) {
            File::put($htmlPath, view('pages.contratos.document', array_merge($payload, [
                'pdfMode' => true,
                'autoPrint' => false,
                'appendixSections' => $appendixSections,
                'renderFooterInBody' => true,
            ]))->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath);
            File::delete($htmlPath);
        }

        if (!$generated || !is_file($pdfPath)) {
            File::delete($pdfPath);
            throw new \RuntimeException('Nao foi possivel gerar o PDF do contrato neste ambiente.');
        }

        if (!empty($appendixSections)) {
            $this->appendAppendixPdf(
                $pdfPath,
                $appendixSections,
                $baseDir,
                $slug,
                $nextVersion,
                $contract->template?->margins_json ?? null,
                (string) ($contract->template?->page_size ?? 'a4'),
                (string) ($contract->template?->page_orientation ?? 'portrait')
            );
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

    private function renderPdfWithMpdf(
        string $htmlPath,
        string $pdfPath,
        ?array $margins = null,
        string $pageSize = 'a4',
        string $orientation = 'portrait',
        ?string $footerTemplatePath = null
    ): bool {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return false;
        }

        $margins = array_merge(['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3], $margins ?? []);
        $footerTemplate = ($footerTemplatePath && is_file($footerTemplatePath)) ? (string) File::get($footerTemplatePath) : '';
        $footerReserveMm = $this->footerReserveMm($footerTemplate);
        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);
        $baseBottomMm = $this->cmToMm((float) ($margins['bottom'] ?? 2));
        $effectiveBottomMm = max($baseBottomMm + $footerReserveMm + 1.5, 24);

        try {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => $tempDir,
                'format' => $this->mpdfPageFormat($pageSize),
                'orientation' => $orientation === 'landscape' ? 'L' : 'P',
                'margin_top' => $this->cmToMm((float) ($margins['top'] ?? 3)),
                'margin_right' => $this->cmToMm((float) ($margins['right'] ?? 2)),
                'margin_bottom' => $effectiveBottomMm,
                'margin_left' => $this->cmToMm((float) ($margins['left'] ?? 3)),
                'margin_footer' => 2,
            ]);

            $mpdf->showImageErrors = false;
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;

            if ($footerTemplate !== '') {
                $mpdf->SetHTMLFooter($this->buildMpdfFooterHtml($footerTemplate));
            }

            $html = (string) File::get($htmlPath);
            $html = preg_replace('/<link[^>]+font-awesome[^>]*>/i', '', $html) ?: $html;
            $mpdf->WriteHTML($html);
            $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

            return is_file($pdfPath);
        } catch (\Throwable) {
            File::delete($pdfPath);
            return false;
        }
    }

    private function appendAppendixPdf(
        string $mainPdfPath,
        array $appendixSections,
        string $baseDir,
        string $slug,
        int $versionNumber,
        ?array $margins = null,
        string $pageSize = 'a4',
        string $orientation = 'portrait'
    ): void {
        if ($appendixSections === [] || !is_file($mainPdfPath)) {
            return;
        }

        $appendixPdfPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $versionNumber, 2, '0', STR_PAD_LEFT) . '-appendices.pdf';
        $mergedPdfPath = $baseDir . DIRECTORY_SEPARATOR . $slug . '-v' . str_pad((string) $versionNumber, 2, '0', STR_PAD_LEFT) . '-merged.pdf';

        try {
            $generated = $this->renderAppendixPdf($appendixSections, $appendixPdfPath, $margins, $pageSize, $orientation);
            if (!$generated || !is_file($appendixPdfPath)) {
                File::delete($appendixPdfPath);
                return;
            }

            if (!$this->mergePdfFiles([$mainPdfPath, $appendixPdfPath], $mergedPdfPath)) {
                File::delete([$appendixPdfPath, $mergedPdfPath]);
                return;
            }

            File::delete($mainPdfPath);
            File::move($mergedPdfPath, $mainPdfPath);
            File::delete($appendixPdfPath);
        } catch (\Throwable) {
            File::delete([$appendixPdfPath, $mergedPdfPath]);
        }
    }

    private function renderAppendixPdf(
        array $appendixSections,
        string $pdfPath,
        ?array $margins = null,
        string $pageSize = 'a4',
        string $orientation = 'portrait'
    ): bool {
        $html = $this->buildAppendixHtml($appendixSections);
        $htmlPath = dirname($pdfPath) . DIRECTORY_SEPARATOR . pathinfo($pdfPath, PATHINFO_FILENAME) . '.html';
        File::put($htmlPath, $html);

        try {
            if (class_exists(\Mpdf\Mpdf::class)) {
                $margins = array_merge(['top' => 2, 'right' => 1.5, 'bottom' => 2, 'left' => 1.5], $margins ?? []);
                $tempDir = storage_path('framework/cache/mpdf');
                File::ensureDirectoryExists($tempDir);

                $mpdf = new \Mpdf\Mpdf([
                    'tempDir' => $tempDir,
                    'format' => $this->mpdfPageFormat($pageSize),
                    'orientation' => $orientation === 'landscape' ? 'L' : 'P',
                    'margin_top' => $this->cmToMm((float) ($margins['top'] ?? 2)),
                    'margin_right' => $this->cmToMm((float) ($margins['right'] ?? 1.5)),
                    'margin_bottom' => $this->cmToMm((float) ($margins['bottom'] ?? 2)),
                    'margin_left' => $this->cmToMm((float) ($margins['left'] ?? 1.5)),
                ]);
                $mpdf->showImageErrors = false;
                $mpdf->WriteHTML($html);
                $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

                return is_file($pdfPath);
            }

            return $this->renderPdfWithChromium($htmlPath, $pdfPath);
        } catch (\Throwable) {
            File::delete($pdfPath);
            return false;
        } finally {
            File::delete($htmlPath);
        }
    }

    private function buildAppendixHtml(array $appendixSections): string
    {
        $pages = [];

        foreach ($appendixSections as $section) {
            foreach (($section['pages'] ?? []) as $page) {
                $pages[] = '
                    <div class="appendix-page">
                        <div class="appendix-title">Documento anexado ao contrato</div>
                        <div class="appendix-subtitle">' . e((string) ($section['original_name'] ?? 'Documento')) . '</div>'
                        . (!empty($section['owner_label']) ? '<div class="appendix-owner">' . e((string) $section['owner_label']) . '</div>' : '') . '
                        <div class="appendix-image-wrap">
                            <img src="' . e((string) ($page['src'] ?? '')) . '" alt="' . e((string) ($section['original_name'] ?? 'Documento anexado')) . '" class="appendix-image">
                        </div>
                    </div>';
            }
        }

        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
            body{font-family:Arial,Helvetica,sans-serif;color:#1f2937;font-size:12px;line-height:1.5;margin:0}
            .appendix-page{page-break-after:always}
            .appendix-page:last-child{page-break-after:auto}
            .appendix-title{font-size:15px;font-weight:700;color:#941415;text-transform:uppercase;letter-spacing:.08em}
            .appendix-subtitle{margin-top:6px;font-size:12px;color:#6b7280}
            .appendix-owner{margin-top:2px;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em}
            .appendix-image-wrap{margin-top:18px;text-align:center}
            .appendix-image{max-width:100%;max-height:240mm;object-fit:contain}
        </style></head><body>' . implode('', $pages) . '</body></html>';
    }

    private function mergePdfFiles(array $paths, string $outputPath): bool
    {
        $binary = $this->availableExecutable(['pdfunite']);
        $files = array_values(array_filter($paths, fn ($path) => is_file((string) $path)));

        if (!$binary || count($files) < 2) {
            return false;
        }

        try {
            $process = new Process(array_merge([$binary], $files, [$outputPath]), timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($outputPath);
        } catch (\Throwable) {
            File::delete($outputPath);
            return false;
        }
    }

    private function renderPdfWithChromiumDevtools(
        string $htmlPath,
        string $pdfPath,
        ?array $margins = null,
        string $pageSize = 'a4',
        string $orientation = 'portrait',
        ?string $footerTemplatePath = null
    ): bool {
        $binary = $this->availableExecutable([
            'chromium',
            'chromium-browser',
            'google-chrome',
            'google-chrome-stable',
        ]);
        $python = $this->availableExecutable(['python3', 'python']);
        $script = base_path('scripts/render_contract_pdf.py');

        if (!$binary || !$python || !is_file($script)) {
            return false;
        }

        $margins = array_merge(['top' => 3, 'right' => 2, 'bottom' => 2, 'left' => 3], $margins ?? []);
        $dimensions = $this->paperDimensionsInches($pageSize, $orientation);
        $footerTemplate = ($footerTemplatePath && is_file($footerTemplatePath)) ? (string) File::get($footerTemplatePath) : '';
        $marginBottomCm = (float) ($margins['bottom'] ?? 2) + $this->footerReserveCm($footerTemplate);

        try {
            $process = new Process([
                $python,
                $script,
                '--chromium',
                $binary,
                '--html',
                $htmlPath,
                '--output',
                $pdfPath,
                '--paper-width',
                (string) $dimensions['width'],
                '--paper-height',
                (string) $dimensions['height'],
                '--margin-top',
                (string) $this->cmToInches((float) ($margins['top'] ?? 3)),
                '--margin-right',
                (string) $this->cmToInches((float) ($margins['right'] ?? 2)),
                '--margin-bottom',
                (string) $this->cmToInches($marginBottomCm),
                '--margin-left',
                (string) $this->cmToInches((float) ($margins['left'] ?? 3)),
                '--footer-template-file',
                $footerTemplatePath ?: '',
            ], timeout: 180);
            $process->run();

            return $process->isSuccessful() && is_file($pdfPath);
        } catch (\Throwable) {
            return false;
        }
    }

    private function renderPdfWithWkhtmltopdf(string $htmlPath, string $pdfPath, ?array $margins = null, string $pageSize = 'a4', string $orientation = 'portrait', ?string $footerHtmlPath = null): bool
    {
        $binary = $this->availableExecutable(['wkhtmltopdf']);
        if (!$binary) {
            return false;
        }

        $margins = array_merge(['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10], $margins ?? []);
        $wkhtmlPageSize = match ($pageSize) {
            'legal' => 'Legal',
            'letter' => 'Letter',
            default => 'A4',
        };
        $wkhtmlOrientation = $orientation === 'landscape' ? 'Landscape' : 'Portrait';

        try {
            $command = [
                $binary,
                '--enable-local-file-access',
                '--encoding',
                'UTF-8',
                '--page-size',
                $wkhtmlPageSize,
                '--orientation',
                $wkhtmlOrientation,
                '--margin-top',
                (string) $margins['top'],
                '--margin-right',
                (string) $margins['right'],
                '--margin-bottom',
                (string) $margins['bottom'],
                '--margin-left',
                (string) $margins['left'],
            ];

            if ($footerHtmlPath && is_file($footerHtmlPath)) {
                $command[] = '--footer-html';
                $command[] = $footerHtmlPath;
                $command[] = '--footer-spacing';
                $command[] = '1';
            }

            $command[] = $htmlPath;
            $command[] = $pdfPath;

            $process = new Process($command, timeout: 120);
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

                $kind = $this->appendixKind($attachment, $path);
                $pages = match ($kind) {
                    'pdf' => $this->pdfPagesToDataUris($path),
                    'image' => array_filter([$this->imageToDataUri($path) ?: $this->fileUri($path)]),
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
        $relativePath = trim(str_replace('\\', '/', (string) ($attachment['relative_path'] ?? '')));
        $storedName = trim((string) ($attachment['stored_name'] ?? ''));

        $candidates = [];
        if ($relativePath !== '') {
            if (Str::startsWith($relativePath, ['/storage/', 'storage/'])) {
                $storageRelative = preg_replace('#^/?storage/#', '', $relativePath) ?: '';
                if ($storageRelative !== '') {
                    $candidates[] = storage_path('app/public/' . ltrim($storageRelative, '/'));
                    $candidates[] = public_path('storage/' . ltrim($storageRelative, '/'));
                }
            } elseif (Str::startsWith($relativePath, ['/uploads/', 'uploads/'])) {
                $candidates[] = public_path(ltrim($relativePath, '/'));
            } else {
                $candidates[] = storage_path('app/public/' . ltrim($relativePath, '/'));
                $candidates[] = public_path(ltrim($relativePath, '/'));
            }
        }

        if ($storedName !== '' && !empty($attachment['owner_type'] ?? null) && !empty($attachment['owner_id'] ?? null)) {
            $ownerType = trim((string) $attachment['owner_type']);
            $ownerId = (int) $attachment['owner_id'];
            $candidates[] = storage_path('app/public/clientes/' . $ownerType . '/' . $ownerId . '/' . $storedName);
            $candidates[] = public_path('uploads/clientes/' . $ownerType . '/' . $ownerId . '/' . $storedName);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
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

    private function appendixKind(array $attachment, string $path): string
    {
        $extension = strtolower((string) pathinfo((string) ($attachment['stored_name'] ?? $attachment['relative_path'] ?? $path), PATHINFO_EXTENSION));
        $mimeType = strtolower((string) ($attachment['mime_type'] ?? ''));

        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return 'pdf';
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true) || str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'unsupported';
    }

    private function fileUri(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $normalized = str_replace('\\', '/', realpath($path) ?: $path);
        $segments = array_map('rawurlencode', explode('/', ltrim($normalized, '/')));

        return 'file:///' . implode('/', $segments);
    }

    private function buildWkhtmlFooterHtml(array $payload): string
    {
        $customFooter = trim((string) ($payload['rendered_footer_html'] ?? ''));
        $content = $customFooter !== ''
            ? $this->normalizeFooterHtmlForWkhtml($customFooter)
            : '<div class="default-footer">' . e((string) ($payload['settings']['footer_text'] ?? 'documento gerado pelo ancora hub')) . '</div>';

        return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>'
            . 'body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#6b7280;}'
            . '.default-footer{border-top:2px solid #941415;padding-top:10px;text-align:center;text-transform:lowercase;}'
            . '.custom-footer{padding-top:8px;}'
            . '.custom-footer table{width:100%;border-collapse:collapse;}'
            . '.custom-footer td,.custom-footer th{border:1px solid #d1d5db;padding:8px;}'
            . '.custom-footer img{max-width:100%;height:auto;}'
            . '</style></head><body>'
            . ($customFooter !== '' ? '<div class="custom-footer">' . $content . '</div>' : $content)
            . '</body></html>';
    }

    private function normalizeFooterHtmlForWkhtml(string $html): string
    {
        return str_replace(
            ['<span class="ancora-page-number"></span>', '<span class="ancora-page-total"></span>'],
            ['[page]', '[topage]'],
            $html
        );
    }

    private function buildChromiumFooterHtml(array $payload): string
    {
        $customFooter = trim((string) ($payload['rendered_footer_html'] ?? ''));

        if ($customFooter !== '') {
            return '<div style="width:100%;padding:0 0 6px 0;font-family:Arial,Helvetica,sans-serif;font-size:9px;color:#4b5563;">'
                . $this->normalizeFooterHtmlForChromium($customFooter)
                . '</div>';
        }

        return '<div style="width:100%;padding:0 0 6px 0;font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#6b7280;">'
            . '<div style="border-top:2px solid #941415;padding-top:6px;text-align:center;text-transform:lowercase;">'
            . e((string) ($payload['settings']['footer_text'] ?? 'documento gerado pelo ancora hub'))
            . '</div></div>';
    }

    private function buildMpdfFooterTemplate(array $payload): string
    {
        $customFooter = trim((string) ($payload['rendered_footer_html'] ?? ''));

        if ($customFooter !== '') {
            return '<div style="width:100%;padding:0 0 2px 0;font-family:Arial,Helvetica,sans-serif;font-size:9px;color:#4b5563;">'
                . $this->buildMpdfFooterHtml($customFooter)
                . '</div>';
        }

        return '<div style="width:100%;padding:0 0 2px 0;font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#6b7280;">'
            . '<div style="border-top:2px solid #941415;padding-top:4px;text-align:center;text-transform:lowercase;">'
            . e((string) ($payload['settings']['footer_text'] ?? 'documento gerado pelo ancora hub'))
            . '</div></div>';
    }

    private function buildMpdfFooterHtml(string $footerHtml): string
    {
        $footerHtml = $this->normalizeFontAwesomeForMpdf($footerHtml);

        return str_replace(
            [
                '<span class="ancora-page-number"></span>',
                '<span class="ancora-page-total"></span>',
                '<span class="pageNumber"></span>',
                '<span class="totalPages"></span>',
            ],
            ['{PAGENO}', '{nbpg}', '{PAGENO}', '{nbpg}'],
            $footerHtml
        );
    }

    private function normalizeFontAwesomeForMpdf(string $html): string
    {
        $map = [
            'fa-phone' => '☎',
            'fa-envelope' => '✉',
            'fa-location-dot' => '📍',
            'fa-map-marker-alt' => '📍',
            'fa-map-pin' => '📍',
            'fa-globe' => '🌐',
            'fa-earth-americas' => '🌐',
            'fa-link' => '🔗',
            'fa-arrow-up-right-from-square' => '↗',
            'fa-external-link-alt' => '↗',
            'fa-whatsapp' => '✆',
            'fa-building' => '🏢',
            'fa-user' => '👤',
            'fa-scale-balanced' => '⚖',
        ];

        return preg_replace_callback('/<i\b[^>]*class="([^"]*)"[^>]*><\/i>/i', function (array $matches) use ($map) {
            $classes = preg_split('/\s+/', trim((string) ($matches[1] ?? ''))) ?: [];
            foreach ($classes as $class) {
                if (isset($map[$class])) {
                    return '<span style="font-weight:700;">' . $map[$class] . '</span>';
                }
            }

            return '';
        }, $html) ?? $html;
    }

    private function normalizeFooterHtmlForChromium(string $html): string
    {
        return str_replace(
            ['<span class="ancora-page-number"></span>', '<span class="ancora-page-total"></span>'],
            ['<span class="pageNumber"></span>', '<span class="totalPages"></span>'],
            $html
        );
    }

    private function footerReserveCm(string $footerHtml): float
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags(str_ireplace(
            ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</tr>'],
            ["\n", "\n", "\n", "\n", "\n", "\n", "\n"],
            $footerHtml
        ))));
        $source = mb_strtolower($footerHtml, 'UTF-8');
        $lineHints = substr_count($source, '<br')
            + substr_count($source, '<tr')
            + substr_count($source, '<p')
            + substr_count($source, '<div')
            + substr_count($source, '</li>');
        $lengthHints = max(0, (int) ceil(max(0, mb_strlen($text, 'UTF-8') - 80) / 80));
        $visualLines = max(1, min(8, $lineHints > 0 ? $lineHints : 1));

        return min(5.2, 0.9 + (($visualLines - 1) * 0.34) + ($lengthHints * 0.2));
    }

    private function footerReserveMm(string $footerHtml): float
    {
        return max(7, round($this->footerReserveCm($footerHtml) * 10, 1));
    }

    private function mpdfPageFormat(string $pageSize): string
    {
        return match ($pageSize) {
            'legal' => 'Legal',
            'letter' => 'Letter',
            default => 'A4',
        };
    }

    private function paperDimensionsInches(string $pageSize, string $orientation): array
    {
        $dimensions = match ($pageSize) {
            'legal' => ['width' => 8.5, 'height' => 14.0],
            'letter' => ['width' => 8.5, 'height' => 11.0],
            default => ['width' => 8.27, 'height' => 11.69],
        };

        if ($orientation === 'landscape') {
            return ['width' => $dimensions['height'], 'height' => $dimensions['width']];
        }

        return $dimensions;
    }

    private function cmToInches(float $value): float
    {
        return round($value * 0.3937007874, 4);
    }

    private function cmToMm(float $value): float
    {
        return round($value * 10, 2);
    }
}
