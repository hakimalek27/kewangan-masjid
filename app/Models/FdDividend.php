<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FdDividend extends Model
{
    protected $table = 'fd_dividend';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'jumlah' => 'decimal:2',
        'tarikh' => 'date:Y-m-d',
    ];
}
