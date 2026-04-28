<?php

namespace App\Services;

use App\Models\ClientAttachment;
use App\Models\ClientEntity;
use App\Models\CobrancaAgreementTerm;
use App\Models\CobrancaCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class CobrancaAgreementPdfService
{
    public function __construct(
        private readonly CobrancaAgreementTermService $termService,
    ) {
    }

    public function generatePersistent(CobrancaCase $case): array
    {
        $case->load([
            'condominium.syndic',
            'block',
            'unit.owner',
            'debtor',
            'contacts',
            'quotas',
            'installments',
        ]);

        $draft = $this->termService->build($case);
        $term = $this->termStorageReady()
            ? CobrancaAgreementTerm::query()->where('cobranca_case_id', $case->id)->first()
            : null;

        $ownerDocument = $this->ownerDocumentAttachment($case);
        $viewData = [
            'case' => $case,
            'title' => $term?->title ?: $draft['title'],
            'bodyText' => $term?->body_text ?: $draft['body_text'],
            'templateType' => $term?->template_type ?: $draft['template_type'],
            'payload' => $term?->payload_json ?: $draft['payload'],
            'ownerDocument' => $ownerDocument ? $this->ownerDocumentViewData($ownerDocument) : null,
            'autoPrint' => false,
            'pdfMode' => true,
        ];

        $dir = storage_path('app/public/cobrancas/' . $case->id . '/signatures');
        File::ensureDirectoryExists($dir);

        $baseName = Str::slug($case->os_number ?: 'termo-acordo') . '-assinatura-' . now()->format('YmdHis') . '-' . Str::random(6);
        $htmlPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.html';
        $pdfPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.pdf';

        try {
            File::put($htmlPath, view('pages.cobrancas.agreement.document', $viewData)->render());

            $generated = $this->renderPdfWithChromium($htmlPath, $pdfPath)
                || $this->renderPdfWithWkhtmltopdf($htmlPath, $pdfPath);

            File::delete($htmlPath);

            if (!$generated || !is_file($pdfPath)) {
                File::delete($pdfPath);
                throw new \RuntimeException('Nao foi possivel gerar o PDF do termo de acordo.');
            }

            $owner = $viewData['ownerDocument'] ?? null;
            if (($owner['type'] ?? null) === 'pdf' && !empty($owner['absolute_path'])) {
                $mergedPath = $dir . DIRECTORY_SEPARATOR . $baseName . '-com-documento.pdf';
                if ($this->appendPdfAttachment($pdfPath, (string) $owner['absolute_path'], $mergedPath)) {
                    File::delete($pdfPath);
                    $pdfPath = $mergedPath;
                }
            }

            return [
                'absolute_path' => $pdfPath,
                'relative_path' => 'cobrancas/' . $case->id . '/signatures/' . basename($pdfPath),
                'filename' => ($case->os_number ?: 'os') . '-termo-acordo.pdf',
            ];
        } catch (\Throwable $e) {
            File::delete($htmlPath);
            File::delete($pdfPath);
            throw $e;
        }
    }

    private function termStorageReady(): bool
    {
        try {
            return Schema::hasTable('cobranca_agreement_terms');
        } catch (\Throwable) {
            return false;
        }
    }

    private function ownerDocumentAttachment(CobrancaCase $case): ?ClientAttachment
    {
        $case->loadMissing('unit.owner');

        if ($case->unit_id) {
            $attachment = $this->latestAppendableClientDocument('unit', (int) $case->unit_id);
            if ($attachment) {
                return $attachment;
            }
        }

        $ownerId = $case->unit?->owner_entity_id ?: $case->debtor_entity_id;
        if ($ownerId) {
            return $this->latestAppendableClientDocument('entity', (int) $ownerId);
        }

        return null;
    }

    private function latestAppendableClientDocument(string $relatedType, int $relatedId): ?ClientAttachment
    {
        return ClientAttachment::query()
            ->where('related_type', $relatedType)
            ->where('related_id', $relatedId)
            ->where('file_role', 'documento')
            ->latest('id')
            ->get()
            ->first(fn (ClientAttachment $attachment) => $this->isAppendableOwnerDocument($attachment));
    }

    private function isAppendableOwnerDocument(ClientAttachment $attachment): bool
    {
        $path = $this->clientAttachmentAbsolutePath($attachment);
        if (!$path || !is_file($path)) {
            return false;
        }

        return in_array($this->clientAttachmentKind($attachment), ['pdf', 'image'], true);
    }

    private function ownerDocumentViewData(ClientAttachment $attachment): array
    {
        return [
            'type' => $this->clientAttachmentKind($attachment),
            'title' => 'Documento do proprietario',
            'original_name' => (string) $attachment->original_name,
            'relative_path' => '/' . ltrim((string) $attachment->relative_path, '/'),
            'absolute_path' => $this->clientAttachmentAbsolutePath($attachment),
        ];
    }

    private function clientAttachmentAbsolutePath(ClientAttachment $attachment): ?string
    {
        $relativePath = trim((string) $attachment->relative_path);
        if ($relativePath === '') {
            return null;
        }

        return public_path(ltrim($relativePath, '/'));
    }

    private function clientAttachmentKind(ClientAttachment $attachment): string
    {
        $extension = strtolower((string) pathinfo((string) ($attachment->stored_name ?: $attachment->relative_path ?: $attachment->original_name), PATHINFO_EXTENSION));
        $mimeType = strtolower((string) $attachment->mime_type);

        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return 'pdf';
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true) || str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'unsupported';
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

    private function appendPdfAttachment(string $termPdfPath, string $attachmentPdfPath, string $mergedPath): bool
    {
        if (!is_file($termPdfPath) || !is_file($attachmentPdfPath)) {
            return false;
        }

        $binary = $this->availableExecutable(['pdfunite']);
        if (!$binary) {
            return false;
        }

        try {
            $process = new Process([$binary, $termPdfPath, $attachmentPdfPath, $mergedPath], timeout: 120);
            $process->run();

            return $process->isSuccessful() && is_file($mergedPath);
        } catch (\Throwable) {
            File::delete($mergedPath);
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
                // Continua tentando outros binarios.
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
}
