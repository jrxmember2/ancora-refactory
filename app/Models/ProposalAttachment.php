<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalAttachment extends Model
{
    protected $table = 'proposta_attachments';

    public $timestamps = false;

    protected $fillable = ['proposta_id', 'original_name', 'stored_name', 'relative_path', 'mime_type', 'file_size', 'uploaded_by', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
