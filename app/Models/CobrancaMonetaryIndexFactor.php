<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CobrancaMonetaryIndexFactor extends Model
{
    protected $table = 'cobranca_monetary_index_factors';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'factor' => 'decimal:10',
        ];
    }
}
