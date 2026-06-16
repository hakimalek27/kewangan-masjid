<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'app_setting';
    // PK komposit (masjid_id, skey) — guna query builder untuk kemas kini
    protected $primaryKey = 'masjid_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
}
