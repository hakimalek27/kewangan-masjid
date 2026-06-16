<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppkmsSync extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'sppkms_sync';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'done_at' => 'datetime',
    ];
}
