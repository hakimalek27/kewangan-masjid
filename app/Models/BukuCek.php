<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuCek extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'buku_cek';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh_keluar' => 'date:Y-m-d',
    ];
}
