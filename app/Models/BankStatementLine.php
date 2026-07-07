<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatementLine extends Model
{
    protected $table = 'bank_statement_line';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'tarikh' => 'date:Y-m-d',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
        'baki' => 'decimal:2',
        'imported_at' => 'datetime',
        'ai_confidence' => 'integer',
    ];

    /** Batch Semak Penyata (AI) — null untuk baris import CSV manual. */
    public function batch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PenyataSemakan::class, 'batch_id');
    }
}
