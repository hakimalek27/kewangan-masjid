<?php

namespace Tests\Feature\Lanjutan;

use App\Services\Lanjutan\YearEndService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * C4 — tutup tahun MESTI menolak jika tahun sebelumnya (yang ada aktiviti) belum
 * ditutup, supaya baki kumulatif tidak dilipat & tempoh tidak terkunci separuh.
 */
class YearEndGuardTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    public function test_tutup_2025_ditolak_jika_2024_belum_tutup(): void
    {
        $servis = app(YearEndService::class);

        // 2024 ada aktiviti (data sejarah) tetapi belum ditutup → tutup 2025 disekat.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tutup tahun 2024 dahulu');
        $servis->tutup(2025);
    }

    public function test_tutup_2025_dibenarkan_selepas_2024_ditutup(): void
    {
        $servis = app(YearEndService::class);

        $servis->tutup(2024);
        $v = $servis->tutup(2025);

        $this->assertSame('YE-2025', $v->voucher_ref);
        $this->assertSame('2025-13', $v->period_ym);
    }
}
