<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialImportLog extends Model
{
    protected $table = 'financial_import_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'errors_json' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function statements(): HasMany
    {
        return $this->hasMany(FinancialStatement::class, 'import_log_id');
    }
}
