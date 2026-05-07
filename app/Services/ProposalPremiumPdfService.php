<?php

namespace App\Services;

use App\Models\Proposal;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class ProposalPremiumPdfService
{
    public function generate(Proposal $proposal): string
    {
        $render = ProposalRenderService::buildByPropostaId((int) $proposal->id);

        if (!$render) {
            throw new \RuntimeException('O documento premium da proposta ainda nao foi salvo.');
        }

        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('A biblioteca mPDF nao esta disponivel neste ambiente.');
        }

        $directory = storage_path('app/temp/proposals');
        File::ensureDirectoryExists($directory);

        $htmlPath = $directory . DIRECTORY_SEPARATOR . Str::uuid() . '.html';
        $pdfPath = $directory . DIRECTORY_SEPARATOR . Str::uuid() . '.pdf';
        $tempDir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($tempDir);

        $preparedRender = $this->prepareRenderForPdf($render);
        $html = view('pages.propostas.documentos.pdf', [
            'render' => $preparedRender,
            'proposta' => $proposal,
            'inlineCss' => $this->inlineCssForPdf(),
        ])->render();
        File::put($htmlPath, $html);

        try {
            $generated = $this->renderPdfWithMpdf($html, $pdfPath, $tempDir)
                || $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);
        } catch (\Throwable $e) {
            File::delete($pdfPath);
            File::delete($htmlPath);

            throw new \RuntimeException('Falha ao renderizar o PDF com mPDF.', previous: $e);
        } finally {
            File::delete($htmlPath);
        }

        if (!$generated || !is_file($pdfPath)) {
            File::delete($pdfPath);
            throw new \RuntimeException('Falha ao renderizar o PDF com mPDF e nenhum fallback conseguiu gerar o arquivo.');
        }

        return $pdfPath;
    }

    public function downloadFilename(Proposal $proposal): string
    {
        $base = $proposal->proposal_code ?: $proposal->client_name ?: 'proposta-premium';

        return Str::slug((string) $base) . '.pdf';
    }

    private function inlineCssForPdf(): string
    {
        $path = public_path('assets/css/proposal-template-aquarela.css');

        if (!is_file($path)) {
            return '';
        }

        $css = (string) File::get($path);
        $variables = [];

        if (preg_match('/:root\s*\{(?P<body>.*?)\}/s', $css, $matches)) {
            preg_match_all('/--([\w-]+)\s*:\s*([^;]+);/', (string) ($matches['body'] ?? ''), $variableMatches, PREG_SET_ORDER);

            foreach ($variableMatches as $variableMatch) {
                $variables[$variableMatch[1]] = trim((string) $variableMatch[2]);
            }

            $css = str_replace($matches[0], '', $css);
        }

        foreach ($variables as $name => $value) {
            $css = str_replace("var(--{$name})", $value, $css);
        }

        $css = preg_replace('/--[\w-]+\s*:\s*[^;]+;/', '', $css) ?: $css;

        return trim($css);
    }

    private function prepareRenderForPdf(array $render): array
    {
        foreach ([
            ['branding', 'logo_light'],
            ['branding', 'logo_dark'],
            ['branding', 'logo_premium'],
            ['assets', 'cover_image_url'],
            ['assets', 'rebeca_image_url'],
        ] as [$group, $field]) {
            $current = data_get($render, $group . '.' . $field);
            data_set($render, $group . '.' . $field, $this->embeddableAsset($current));
        }

        return $render;
    }

    private function embeddableAsset(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'data:')) {
            return $value;
        }

        $localPath = $this->resolveLocalAssetPath($value);
        if (!$localPath || !is_file($localPath)) {
            return $value;
        }

        $mime = File::mimeType($localPath) ?: $this->mimeFromExtension($localPath);
        $contents = File::get($localPath);

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function resolveLocalAssetPath(string $value): ?string
    {
        if (preg_match('~^https?://~i', $value)) {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $candidate = public_path(ltrim($path, '/'));
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        $normalized = ltrim(str_replace('\\', '/', $value), '/');
        $candidate = public_path($normalized);

        return is_file($candidate) ? $candidate : null;
    }

    private function mimeFromExtension(string $path): string
    {
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    private function renderPdfWithMpdf(string $html, string $pdfPath, string $tempDir): bool
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return false;
        }

        try {
            $mpdf = new \Mpdf\Mpdf([
                'tempDir' => $tempDir,
                'format' => 'A4',
                'orientation' => 'P',
                'margin_top' => 0,
                'margin_right' => 0,
                'margin_bottom' => 0,
                'margin_left' => 0,
            ]);

            $mpdf->showImageErrors = false;
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            $mpdf->WriteHTML($html);
            $mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

            return is_file($pdfPath);
        } catch (\Throwable) {
            File::delete($pdfPath);
            return false;
        }
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

    private function renderPdfWithWkhtmltopdf(string $htmlPath, string $pdfPath): bool
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
                // segue tentando
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
                // segue tentando
            }
        }

        return null;
    }
}
