<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $table = 'login_attempt';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'success' => 'boolean',
    ];
}
