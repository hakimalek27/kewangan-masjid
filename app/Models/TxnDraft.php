<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TxnDraft extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'txn_draft';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh' => 'date:Y-m-d',
        'jumlah' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];
}
