<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ElectronicSignatureDocument extends Model
{
    use SoftDeletes;

    protected $table = 'electronic_signature_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function signatureRequests(): MorphMany
    {
        return $this->morphMany(DocumentSignatureRequest::class, 'signable')->orderByDesc('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEntity::class, 'client_entity_id');
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(ClientCondominium::class, 'client_condominium_id');
    }
}
