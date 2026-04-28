<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentSignatureRequest extends Model
{
    protected $table = 'document_signature_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'summary_json' => 'array',
            'provider_payload_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signers(): HasMany
    {
        return $this->hasMany(DocumentSignatureSigner::class, 'signature_request_id')->orderBy('order_index')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentSignatureEvent::class, 'signature_request_id')->orderByDesc('received_at')->orderByDesc('id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class, 'document_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
