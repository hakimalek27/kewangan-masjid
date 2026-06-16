<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'backup_log';
    const UPDATED_AT = null;
    protected $guarded = [];
}
