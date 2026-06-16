<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'api_client';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];
}
