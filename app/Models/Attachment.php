<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'attachment';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'uploaded_at' => 'datetime',
    ];
}
