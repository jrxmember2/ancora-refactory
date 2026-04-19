<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessCaseOption extends Model
{
    protected $table = 'process_case_options';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order')->orderBy('name');
    }
}
