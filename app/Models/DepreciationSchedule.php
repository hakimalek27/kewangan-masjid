<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepreciationSchedule extends Model
{
    protected $table = 'depreciation_schedule';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'amaun' => 'decimal:2',
        'posted' => 'boolean',
    ];
}
