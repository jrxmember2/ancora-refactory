<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalDocumentOption extends Model
{
    protected $table = 'proposal_document_options';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_value' => 'decimal:2',
            'is_recommended' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
