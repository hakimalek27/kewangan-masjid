<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    protected $table = 'api_request_log';
    const UPDATED_AT = null;
    protected $guarded = [];
}
