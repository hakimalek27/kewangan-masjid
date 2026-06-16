<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kutipan extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'kutipan';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh' => 'date:Y-m-d',
        'jumlah' => 'decimal:2',
        'auto_resit' => 'boolean',
        'tar_bankin' => 'date:Y-m-d',
        'tar_kira' => 'date:Y-m-d',
    ];

    public function coa(): BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id')->withoutGlobalScope('masjid');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id')->withoutGlobalScope('masjid');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'voucher_id')->withoutGlobalScope('masjid');
    }

    public function fd(): BelongsTo
    {
        return $this->belongsTo(FdInvestment::class, 'fd_id')->withoutGlobalScope('masjid');
    }

    public function denominasi(): HasMany
    {
        return $this->hasMany(KutipanDenominasi::class, 'kutipan_id');
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status', 'ACTIVE');
    }
}
