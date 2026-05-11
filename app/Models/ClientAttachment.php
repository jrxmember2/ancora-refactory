<?php

namespace App\Models;

use App\Support\AiDocumentCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientAttachment extends Model
{
    protected $table = 'client_attachments';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'document_date' => 'date',
            'ai_processed_at' => 'datetime',
        ];
    }

    public function extension(): string
    {
        $candidate = trim((string) pathinfo((string) ($this->stored_name ?: $this->original_name ?: $this->relative_path), PATHINFO_EXTENSION));

        return strtolower($candidate);
    }

    public function isDocx(): bool
    {
        return $this->extension() === 'docx';
    }

    public function condominiumDocumentKind(): ?string
    {
        if ($this->related_type !== 'condominium') {
            return null;
        }

        return AiDocumentCatalog::classifyCondominiumAttachmentName((string) $this->original_name);
    }

    public function canProcessForAi(): bool
    {
        return $this->condominiumDocumentKind() !== null && $this->isDocx();
    }

    public function aiProcessingStatusKey(): ?string
    {
        $value = trim((string) $this->ai_processing_status);
        if ($value !== '') {
            return $value;
        }

        return $this->canProcessForAi() ? AiDocumentCatalog::STATUS_PENDING : null;
    }

    public function aiProcessingStatusLabel(): string
    {
        return AiDocumentCatalog::processingStatusLabel($this->aiProcessingStatusKey());
    }

    public function publicUrl(): string
    {
        $relativePath = trim((string) $this->relative_path);

        if ($relativePath === '') {
            return '';
        }

        if (Str::startsWith($relativePath, ['http://', 'https://'])) {
            return $relativePath;
        }

        if (Str::startsWith($relativePath, ['/storage/', 'storage/', '/uploads/', 'uploads/'])) {
            return '/' . ltrim($relativePath, '/');
        }

        return '/storage/' . ltrim($relativePath, '/');
    }

    public function absolutePath(): ?string
    {
        $relativePath = trim(str_replace('\\', '/', (string) $this->relative_path));
        $storedName = trim((string) $this->stored_name);
        $relatedType = trim((string) $this->related_type);
        $relatedId = (int) $this->related_id;

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

        if ($storedName !== '' && $relatedType !== '' && $relatedId > 0) {
            $candidates[] = storage_path('app/public/clientes/' . $relatedType . '/' . $relatedId . '/' . $storedName);
            $candidates[] = public_path('uploads/clientes/' . $relatedType . '/' . $relatedId . '/' . $storedName);
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
