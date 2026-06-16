<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookSubscription extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'webhook_subscription';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
