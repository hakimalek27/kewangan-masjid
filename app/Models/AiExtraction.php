<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiExtraction extends Model
{
    protected $table = 'ai_extraction';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'ex_tarikh' => 'date:Y-m-d',
        'ex_jumlah' => 'decimal:2',
        'raw_json' => 'array',
        'confidence' => 'decimal:2',
        'cost_usd' => 'decimal:2',
    ];
}
