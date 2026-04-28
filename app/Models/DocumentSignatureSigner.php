<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSignatureSigner extends Model
{
    protected $table = 'document_signature_signers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'requested_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'last_event_at' => 'datetime',
            'provider_payload_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentSignatureRequest::class, 'signature_request_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentSignatureEvent::class, 'signature_signer_id')->orderByDesc('received_at')->orderByDesc('id');
    }
}
