<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaCaseEmailHistory extends Model
{
    protected $table = 'cobranca_case_email_histories';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'recipients_json' => 'array',
            'attachment_file_size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function cobrancaCase(): BelongsTo
    {
        return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id');
    }

    public function monetaryUpdate(): BelongsTo
    {
        return $this->belongsTo(CobrancaMonetaryUpdate::class, 'cobranca_monetary_update_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
