<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalHistory extends Model
{
    protected $table = 'proposta_history';

    public $timestamps = false;

    protected $fillable = ['proposta_id', 'user_id', 'user_email', 'action', 'summary', 'payload_json', 'created_at'];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
