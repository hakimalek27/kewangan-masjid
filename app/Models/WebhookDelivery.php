<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $table = 'webhook_delivery';
    const UPDATED_AT = null;
    protected $guarded = [];
}
