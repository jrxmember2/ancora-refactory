<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientUnit extends Model
{
    protected $table = 'client_units';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
