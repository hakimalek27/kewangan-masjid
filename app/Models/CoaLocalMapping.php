<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoaLocalMapping extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'coa_local_mapping';
    public $timestamps = false;
    protected $guarded = [];
}
