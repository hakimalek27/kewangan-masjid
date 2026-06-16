<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundAccount extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'fund_account';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'allow_deficit' => 'boolean',
    ];
}
