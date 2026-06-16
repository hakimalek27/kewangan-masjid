<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CekBatal extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'cek_batal';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh_batal' => 'date:Y-m-d',
        'amaun' => 'decimal:2',
        'tarikh_ganti' => 'date:Y-m-d',
    ];
}
