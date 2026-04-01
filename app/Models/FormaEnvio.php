<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormaEnvio extends Model
{
    protected $table = 'formas_envio';

    protected $fillable = ['name', 'icon_class', 'color_hex', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order')->orderBy('name');
    }
}
