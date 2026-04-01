<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalTemplate extends Model
{
    protected $table = 'proposal_templates';

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('name');
    }
}
