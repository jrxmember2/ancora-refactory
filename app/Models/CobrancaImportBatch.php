<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobrancaImportBatch extends Model
{
    protected $table = 'cobranca_import_batches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(CobrancaImportRow::class, 'batch_id')->orderBy('row_number');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
