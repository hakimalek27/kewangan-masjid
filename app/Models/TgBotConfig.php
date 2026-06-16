<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TgBotConfig extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'tg_bot_config';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
