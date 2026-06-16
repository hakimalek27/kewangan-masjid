<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupConfig extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'backup_config';
    protected $primaryKey = 'masjid_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'encrypt' => 'boolean',
        'is_active' => 'boolean',
        'last_backup_at' => 'datetime',
    ];
}
