<?php

namespace App\Services;

use App\Models\DocumentSignatureRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentSignatureDownloadService
{
    public function __construct(
        private readonly DocumentSignatureService $signatureService,
    ) {
    }

    public function downloadArtifact(
        DocumentSignatureRequest $signature,
        string $artifact,
        User $user,
        string $backUrl
    ): BinaryFileResponse|RedirectResponse {
        $meta = match ($artifact) {
            'original' => ['field' => 'local_pdf_path', 'filename' => trim((string) $signature->document_name) ?: 'documento-original.pdf'],
            'signed' => ['field' => 'signed_pdf_path', 'filename' => 'documento-assinado.pdf'],
            'certificate' => ['field' => 'certificate_pdf_path', 'filename' => 'certificado-assinatura.pdf'],
            'bundle' => ['field' => 'bundle_pdf_path', 'filename' => 'pacote-assinatura.pdf'],
            default => null,
        };

        if (!$meta) {
            abort(404);
        }

        if (in_array($artifact, ['signed', 'certificate', 'bundle'], true) && trim((string) $signature->{$meta['field']}) === '') {
            try {
                $signature = $this->signatureService->syncRequest($signature, null, null, $user);
            } catch (\Throwable $e) {
                return redirect($backUrl)->with('error', 'Nao foi possivel preparar o download do documento assinado: ' . $e->getMessage());
            }
        }

        $relativePath = trim((string) $signature->{$meta['field']});
        if ($relativePath === '') {
            return redirect($backUrl)->with('error', 'Este arquivo ainda nao esta disponivel para download.');
        }

        $path = $this->resolveStoredPath($relativePath);
        if (!$path) {
            return redirect($backUrl)->with('error', 'O arquivo solicitado nao foi localizado no servidor.');
        }

        return response()->download($path, $meta['filename']);
    }

    private function resolveStoredPath(string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));
        if ($relativePath === '') {
            return null;
        }

        $candidates = [];

        try {
            $candidates[] = Storage::disk('local')->path($relativePath);
        } catch (\Throwable) {
            // Fallback manual abaixo cobre ambientes em que o disk local nao exponha path().
        }

        if (Str::startsWith($relativePath, ['/storage/', 'storage/'])) {
            $storageRelative = preg_replace('#^/?storage/#', '', $relativePath) ?: '';
            if ($storageRelative !== '') {
                $candidates[] = storage_path('app/public/' . ltrim($storageRelative, '/'));
                $candidates[] = public_path('storage/' . ltrim($storageRelative, '/'));
            }
        } elseif (Str::startsWith($relativePath, ['/uploads/', 'uploads/'])) {
            $candidates[] = public_path(ltrim($relativePath, '/'));
        } else {
            $candidates[] = storage_path('app/private/' . ltrim($relativePath, '/'));
            $candidates[] = storage_path('app/public/' . ltrim($relativePath, '/'));
            $candidates[] = storage_path('app/' . ltrim($relativePath, '/'));
            $candidates[] = public_path(ltrim($relativePath, '/'));
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
