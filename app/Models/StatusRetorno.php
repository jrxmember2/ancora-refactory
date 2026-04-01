<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusRetorno extends Model
{
    protected $table = 'status_retorno';

    protected $fillable = [
        'system_key', 'name', 'color_hex', 'requires_closed_value', 'requires_refusal_reason', 'stop_followup_alert', 'is_active', 'sort_order'
    ];

    protected function casts(): array
    {
        return [
            'requires_closed_value' => 'boolean',
            'requires_refusal_reason' => 'boolean',
            'stop_followup_alert' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order')->orderBy('name');
    }
}
