<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenyataSignature extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'penyata_signature';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'aktif' => 'boolean',
    ];
}
