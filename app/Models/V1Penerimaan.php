<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class V1Penerimaan extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'v1_penerimaan';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'tarikh' => 'date:Y-m-d',
        'jumlah' => 'decimal:2',
    ];
}
