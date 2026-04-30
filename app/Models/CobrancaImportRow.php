<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaImportRow extends Model
{
    protected $table = 'cobranca_import_rows';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload_json' => 'array',
            'issue_payload_json' => 'array',
            'resolution_payload_json' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'amount_value' => 'decimal:2',
            'matched_unit_id' => 'integer',
            'matched_case_id' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CobrancaImportBatch::class, 'batch_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClientUnit::class, 'matched_unit_id');
    }

    public function cobrancaCase(): BelongsTo
    {
        return $this->belongsTo(CobrancaCase::class, 'matched_case_id');
    }
}
