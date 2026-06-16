<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'budget';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'amaun_peruntukan' => 'decimal:2',
    ];
}
