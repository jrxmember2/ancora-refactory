<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaCaseTimeline extends Model
{
    protected $table = 'cobranca_case_timelines';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function cobrancaCase(): BelongsTo { return $this->belongsTo(CobrancaCase::class, 'cobranca_case_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
