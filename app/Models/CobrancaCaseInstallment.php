<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaCaseInstallment extends Model
{
    protected $table = 'cobranca_case_installments';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function cobrancaCase(): BelongsTo { return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id'); }
}
