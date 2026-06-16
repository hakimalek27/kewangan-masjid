<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'security_event';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'alerted' => 'boolean',
    ];
}
