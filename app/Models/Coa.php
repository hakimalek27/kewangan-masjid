<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    use \App\Models\Concerns\BelongsToMasjid;

    protected $table = 'coa';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'is_header' => 'boolean',
        'is_contra' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopePostable(Builder $q): Builder
    {
        return $q->where('is_header', 0)->where('is_active', 1);
    }

    public function scopeJenis(Builder $q, string $jenis): Builder
    {
        return $q->where('jenis', $jenis);
    }

    /** Julat kod: 100 Ekuiti, 200 Aset Tetap, 250 Aset Semasa, 300 Liabiliti, 400/450 Hasil, 600/650 Belanja */
    public function julat(): string
    {
        return substr($this->kod, 0, 3);
    }

    public function paparan(): string
    {
        return $this->kod.' '.$this->nama;
    }
}
