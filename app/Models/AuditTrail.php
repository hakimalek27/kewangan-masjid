<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'audit_trail';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];
}
