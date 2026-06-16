<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sewaan extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'sewaan';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'kadar_sewa' => 'decimal:2',
        'deposit_amaun' => 'decimal:2',
    ];
}
