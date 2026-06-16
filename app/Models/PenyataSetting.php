<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenyataSetting extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'penyata_setting';
    protected $primaryKey = 'masjid_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
}
