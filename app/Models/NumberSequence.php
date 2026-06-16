<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'number_sequence';
    public $timestamps = false;
    protected $guarded = [];
}
