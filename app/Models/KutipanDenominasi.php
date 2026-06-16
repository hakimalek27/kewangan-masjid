<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KutipanDenominasi extends Model
{
    protected $table = 'kutipan_denominasi';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'denominasi' => 'decimal:2',
        'jumlah' => 'decimal:2',
    ];
}
