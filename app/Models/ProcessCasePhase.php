<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessCasePhase extends Model
{
    protected $table = 'process_case_phases';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'phase_date' => 'date',
            'is_private' => 'boolean',
            'is_reviewed' => 'boolean',
            'datajud_payload_json' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function processCase(): BelongsTo { return $this->belongsTo(ProcessCase::class, 'process_case_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function attachments(): HasMany { return $this->hasMany(ProcessCaseAttachment::class, 'phase_id'); }
}
