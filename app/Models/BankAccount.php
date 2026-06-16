<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'bank_account';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'digunakan' => 'boolean',
    ];
}
