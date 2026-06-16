<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pembayaran extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'pembayaran';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tar_mohon' => 'date:Y-m-d',
        'tar_lulus' => 'date:Y-m-d',
        'jumlah' => 'decimal:2',
    ];

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id')->withoutGlobalScope('masjid');
    }

    public function pwrCoa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'pwr_coa_id')->withoutGlobalScope('masjid');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id')->withoutGlobalScope('masjid');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'voucher_id')->withoutGlobalScope('masjid');
    }

    public function aset(): HasOne
    {
        return $this->hasOne(FixedAsset::class, 'pembayaran_id')->withoutGlobalScope('masjid');
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status', 'ACTIVE');
    }
}
