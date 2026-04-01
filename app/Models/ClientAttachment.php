<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAttachment extends Model
{
    protected $table = 'client_attachments';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
