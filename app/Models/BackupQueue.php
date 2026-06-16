<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupQueue extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'backup_queue';
    const UPDATED_AT = null;
    protected $guarded = [];
}
