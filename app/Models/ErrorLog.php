<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'error_log';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'resolved' => 'boolean',
    ];
}
