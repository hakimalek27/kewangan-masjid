<?php

namespace Tests\Feature\Lanjutan;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Susut nilai bulanan berjadual (Fasa 9) — pastikan jadual 'susut-nilai-bulanan'
 * terdaftar dalam routes/console.php. Logik penjanaan diuji oleh LanjutanTest
 * (DepreciationService::janaBulan idempoten).
 */
class SusutNilaiJadualTest extends TestCase
{
    public function test_jadual_susut_nilai_bulanan_terdaftar(): void
    {
        $schedule = app(Schedule::class);

        $nama = collect($schedule->events())
            ->map(fn ($e) => $e->description)
            ->filter()
            ->values();

        $this->assertTrue(
            $nama->contains('susut-nilai-bulanan'),
            "Jadual 'susut-nilai-bulanan' mesti terdaftar dalam routes/console.php",
        );
    }
}
