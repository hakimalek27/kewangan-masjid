<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCallLog extends Model
{
    protected $table = 'ai_call_log';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'ok' => 'boolean',
    ];
}
