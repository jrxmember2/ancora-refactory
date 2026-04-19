<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessCaseAttachment extends Model
{
    protected $table = 'process_case_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function processCase(): BelongsTo { return $this->belongsTo(ProcessCase::class, 'process_case_id'); }
    public function phase(): BelongsTo { return $this->belongsTo(ProcessCasePhase::class, 'phase_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
