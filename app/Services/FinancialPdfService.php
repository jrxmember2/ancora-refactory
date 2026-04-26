<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class FinancialPdfService
{
    public function renderViewToPdf(string $view, array $payload, string $directory, string $basename): ?string
    {
        File::ensureDirectoryExists($directory);

        $htmlPath = $directory . DIRECTORY_SEPARATOR . $basename . '.html';
        $pdfPath = $directory . DIRECTORY_SEPARATOR . $basename . '.pdf';

        File::put($htmlPath, view($view, $payload)->render());

        $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
            || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

        File::delete($htmlPath);

        if (!$generated || !is_file($pdfPath)) {
            File::delete($pdfPath);
            return null;
        }

        return $pdfPath;
    }

    private function renderPdfWithChromium(string $htmlPath, string $pdfPath): bool
    {
        $binary = $this->availableExecutable(['chromium', 'chromium-browser', 'google-chrome', 'google-chrome-stable']);
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
                '10',
                '--margin-right',
                '10',
                '--margin-bottom',
                '10',
                '--margin-left',
                '10',
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
                // segue
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
                // segue
            }
        }

        return null;
    }
}
