<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignatureEvent extends Model
{
    protected $table = 'document_signature_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'received_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentSignatureRequest::class, 'signature_request_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(DocumentSignatureSigner::class, 'signature_signer_id');
    }
}
