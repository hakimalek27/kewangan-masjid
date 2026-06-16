<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocInbox extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'doc_inbox';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'received_at' => 'datetime',
    ];
}
