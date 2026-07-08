<?php

namespace App\Services\Ai;

use App\Models\PenyataSemakan;
use App\Support\Setting;

/**
 * Kuota ciri "Semak Penyata (AI)" — lalai 3 kali/bulan setiap tenant,
 * dikawal SUPERADMIN sahaja (had per-tenant, top-up bulan semasa, ON/OFF global).
 *
 * Semantik TEMPAHAN: batch UPLOADED/AI_PROCESSING/SEDIA dikira sebagai guna
 * (sekat pintasan muat naik selari); batch GAGAL membebaskan kuota.
 * Rollover bulan automatik — kiraan ikut created_at, tiada job reset.
 */
class KuotaPenyataService
{
    /** Sentinel masjid_id untuk tetapan GLOBAL dalam app_setting (tiada FK — selamat). */
    public const MASJID_GLOBAL = 0;

    public const KUOTA_LALAI = 3;

    public function globallyEnabled(): bool
    {
        return Setting::isOn('semak_penyata_enabled', self::MASJID_GLOBAL);
    }

    /** Had bulanan efektif tenant (override per-tenant ?? lalai 3). 0 = ciri dimatikan untuk tenant. */
    public function effectiveLimit(int $masjidId): int
    {
        $nilai = Setting::get('sp_kuota_bulanan', null, $masjidId);

        return $nilai === null ? self::KUOTA_LALAI : max(0, (int) $nilai);
    }

    /** Top-up tambahan bulan semasa sahaja (kunci bersuffix YYYY-MM — luput sendiri). */
    public function topupBulanIni(int $masjidId): int
    {
        return max(0, (int) Setting::get('sp_topup_'.now()->format('Y-m'), '0', $masjidId));
    }

    /**
     * Guna bulan ini = batch UPLOADED/AI_PROCESSING/SEDIA/DIPADAM. GAGAL & DIBATAL
     * TIDAK dikira (dibebaskan). DIPADAM dikira kerana scan SUDAH digunakan —
     * memadam fail tidak memulihkan kuota (elak pintas had bulanan).
     */
    public function usedThisMonth(int $masjidId): int
    {
        return PenyataSemakan::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->whereIn('status', ['UPLOADED', 'AI_PROCESSING', 'SEDIA', 'DIPADAM'])
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    public function remaining(int $masjidId): int
    {
        return max(0, $this->effectiveLimit($masjidId) + $this->topupBulanIni($masjidId) - $this->usedThisMonth($masjidId));
    }

    /** Boleh muat naik sekarang? (global ON + had > 0 + baki > 0) */
    public function boleh(int $masjidId): bool
    {
        return $this->globallyEnabled()
            && $this->effectiveLimit($masjidId) > 0
            && $this->remaining($masjidId) > 0;
    }
}
