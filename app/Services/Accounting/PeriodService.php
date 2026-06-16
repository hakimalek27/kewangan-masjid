<?php

namespace App\Services\Accounting;

use App\Exceptions\PeriodLockedException;
use App\Support\Setting;

/**
 * Kunci tempoh perakaunan. Bulan/tahun yang sudah ditutup tidak menerima
 * transaksi baharu mahupun pembatalan (jejak audit tahun lepas terpelihara).
 */
class PeriodService
{
    public const KEY = 'period_locked_until';

    /** @throws PeriodLockedException */
    public function assertOpen(string $periodYm, ?int $masjidId = null): void
    {
        $lockedUntil = Setting::get(self::KEY, null, $masjidId);

        if ($lockedUntil !== null && $periodYm <= $lockedUntil) {
            throw new PeriodLockedException($periodYm);
        }
    }

    public function lockedUntil(?int $masjidId = null): ?string
    {
        return Setting::get(self::KEY, null, $masjidId);
    }

    /** Kunci semua tempoh sehingga (dan termasuk) YYYY-MM. */
    public function lockUntil(string $periodYm, ?int $masjidId = null): void
    {
        Setting::set(self::KEY, $periodYm, $masjidId);
    }

    public function unlock(?int $masjidId = null): void
    {
        Setting::set(self::KEY, null, $masjidId);
    }
}
