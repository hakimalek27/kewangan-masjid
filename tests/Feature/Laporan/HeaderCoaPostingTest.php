<?php

namespace Tests\Feature\Laporan;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * C8 — TIADA entri jurnal POSTED boleh merujuk COA KEPALA (is_header=1).
 * Entri warisan V1 pada 600-12000/600-15000 telah di-re-point ke COA anak
 * supaya penutupan tahun (yang langkau is_header) menutupnya dengan betul.
 */
class HeaderCoaPostingTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    public function test_tiada_entri_posted_ke_coa_kepala(): void
    {
        $bil = DB::table('journal_entry as je')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->where('c.is_header', 1)
            ->where('jv.status', 'POSTED')
            ->count();

        $this->assertSame(0, $bil, 'Terdapat entri POSTED ke COA kepala — patut di-re-point ke anak.');
    }
}
