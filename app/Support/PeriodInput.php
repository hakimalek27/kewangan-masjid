<?php

namespace App\Support;

/**
 * Bina period_ym 'YYYY-MM' daripada input pengguna dengan SELAMAT — bulan dikepit
 * ke julat operasi 01..12. Tanpa ini, ?bln=13 / ?bln=0 menghasilkan period 'YYYY-13'
 * / 'YYYY-00' yang (selepas tutup tahun) menarik voucher penutupan/baki awal ke dalam
 * laporan bulanan (C5).
 */
class PeriodInput
{
    public static function ym(int|string|null $year, int|string|null $bln): string
    {
        $y = (int) ($year ?: now()->year);
        $b = max(1, min(12, (int) ($bln ?: now()->month)));

        return sprintf('%04d-%02d', $y, $b);
    }
}
