<?php

namespace App\Models;

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
        ];
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
