<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PetiBesi extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'peti_besi';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh_masuk' => 'date:Y-m-d',
        'tarikh_keluar' => 'date:Y-m-d',
    ];
}
