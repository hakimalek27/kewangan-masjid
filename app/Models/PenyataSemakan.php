<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Batch "Semak Penyata (AI)" — satu muat naik penyata bank (PDF/imej)
 * yang diproses AI pusat. Status: UPLOADED → AI_PROCESSING → SEDIA | GAGAL.
 * Kuota bulanan dikira daripada jadual ini (lihat KuotaPenyataService).
 */
class PenyataSemakan extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'penyata_semakan';
    protected $guarded = [];

    protected $casts = [
        'tokens_used' => 'integer',
        'cost_usd' => 'decimal:4',
        'bil_baris' => 'integer',
        'bil_auto_padan' => 'integer',
    ];

    public function baris(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'batch_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
