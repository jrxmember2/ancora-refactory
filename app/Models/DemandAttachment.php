<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandAttachment extends Model
{
    protected $table = 'demand_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_internal' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function demand(): BelongsTo { return $this->belongsTo(Demand::class, 'demand_id'); }
    public function message(): BelongsTo { return $this->belongsTo(DemandMessage::class, 'message_id'); }
    public function portalUser(): BelongsTo { return $this->belongsTo(ClientPortalUser::class, 'client_portal_user_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }

    public function resolvedAbsolutePath(): ?string
    {
        $path = trim((string) ($this->relative_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'private://')) {
            return storage_path('app/' . ltrim(substr($path, 10), '/'));
        }

        return public_path(ltrim($path, '/'));
    }
}
