<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaAgreementTerm extends Model
{
    protected $table = 'cobranca_agreement_terms';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'printed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function cobrancaCase(): BelongsTo
    {
        return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id');
    }
}
