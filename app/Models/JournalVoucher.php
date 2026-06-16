<?php

namespace App\Models;

use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalVoucher extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'journal_voucher';
    const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = [
        'tarikh' => 'date:Y-m-d',
        'voided_at' => 'datetime',
        'source_type' => SourceType::class,
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'voucher_id');
    }

    public function pencipta(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'created_by');
    }

    public function scopePosted(Builder $q): Builder
    {
        return $q->where('status', 'POSTED');
    }

    public function isVoid(): bool
    {
        return $this->status === 'VOID';
    }
}
