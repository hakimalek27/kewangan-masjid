<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FdInvestment extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'fd_investment';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh' => 'date:Y-m-d',
        'jumlah' => 'decimal:2',
        'kadar_pct' => 'decimal:2',
        'maturity_date' => 'date:Y-m-d',
        'is_opening' => 'boolean',
    ];
}
