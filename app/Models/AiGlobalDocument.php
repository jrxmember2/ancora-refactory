<?php

namespace App\Models;

use App\Support\AiLegalDocumentCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiGlobalDocument extends Model
{
    protected $table = 'ai_global_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'file_size' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiDocumentChunk::class, 'ai_global_document_id');
    }

    public function documentTypeLabel(): string
    {
        return AiLegalDocumentCatalog::documentTypeLabel($this->document_type);
    }

    public function processingStatusLabel(): string
    {
        return AiLegalDocumentCatalog::processingStatusLabel($this->processing_status);
    }

    public function extension(): string
    {
        $candidate = trim((string) pathinfo((string) ($this->stored_name ?: $this->original_name ?: $this->relative_path), PATHINFO_EXTENSION));

        return strtolower($candidate);
    }

    public function isDocx(): bool
    {
        return $this->extension() === AiLegalDocumentCatalog::processableExtension();
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

        if (Str::startsWith($relativePath, ['/storage/', 'storage/'])) {
            return '/' . ltrim($relativePath, '/');
        }

        return '/storage/' . ltrim($relativePath, '/');
    }

    public function absolutePath(): ?string
    {
        $relativePath = trim(str_replace('\\', '/', (string) $this->relative_path));
        if ($relativePath === '') {
            return null;
        }

        $candidates = [];
        if (Str::startsWith($relativePath, ['/storage/', 'storage/'])) {
            $storageRelative = preg_replace('#^/?storage/#', '', $relativePath) ?: '';
            if ($storageRelative !== '') {
                $candidates[] = storage_path('app/public/' . ltrim($storageRelative, '/'));
                $candidates[] = public_path('storage/' . ltrim($storageRelative, '/'));
            }
        } else {
            $candidates[] = storage_path('app/public/' . ltrim($relativePath, '/'));
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
