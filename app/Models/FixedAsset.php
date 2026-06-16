<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'fixed_asset';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh_perolehan' => 'date:Y-m-d',
        'kos' => 'decimal:2',
        'accumulated_depn' => 'decimal:2',
        'depn_rate_pct' => 'decimal:2',
        'donation_value' => 'decimal:2',
    ];
}
