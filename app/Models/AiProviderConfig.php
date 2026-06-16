<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderConfig extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'ai_provider_config';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'supports_vision' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];
}
