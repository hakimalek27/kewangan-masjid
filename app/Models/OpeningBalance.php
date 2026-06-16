<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningBalance extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'opening_balance';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'amaun' => 'decimal:2',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];
}
