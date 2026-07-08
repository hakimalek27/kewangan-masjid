<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Profil provider AI GLOBAL untuk "Semak Penyata (AI)" (dikawal superadmin).
 * Semua tenant kongsi senarai ini; bendahari pilih satu semasa muat naik untuk
 * banding kualiti OCR. Kunci API disimpan di secret_vault (hanya ref di sini).
 * BUKAN berskop masjid — profil dikongsi seluruh platform (penyedia).
 */
class SpProvider extends Model
{
    protected $table = 'sp_provider';
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'kos_input_1k' => 'decimal:6',
        'kos_output_1k' => 'decimal:6',
    ];

    /** Provider aktif untuk dropdown pilihan (default dahulu). */
    public function scopeAktif($query)
    {
        return $query->where('is_active', true)->orderByDesc('is_default')->orderBy('nama');
    }

    /** Label ringkas untuk paparan/rekod batch. */
    public function label(): string
    {
        return $this->nama.' ('.$this->model.')';
    }
}
