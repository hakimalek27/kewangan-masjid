<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'approval';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'amaun' => 'decimal:2',
        'made_at' => 'datetime',
        'decided_at' => 'datetime',
    ];
}
