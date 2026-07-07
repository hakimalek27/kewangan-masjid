<?php

namespace Tests\Feature\Laporan;

use App\Enums\SourceType;
use App\Services\Accounting\JournalService;
use App\Services\Laporan\ReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * B3 — P&L untuk julat MERENTAS TAHUN mesti kecualikan voucher penutupan ('YYYY-13')
 * dan baki awal ('YYYY-00'). Jika tidak, julat 2024-01..2025-12 menyerap YE-2024
 * → Hasil/Belanja 2024 terherot.
 */
class ProfitLossTutupTahunTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    public function test_profitloss_merentas_tahun_kecualikan_period_13_dan_00(): void
    {
        $mid = config('spkm.masjid_id');
        $js = app(JournalService::class);
        $rs = app(ReportService::class);

        $hasilKod = '400-01010';
        $belanjaKod = '600-01000';

        $sebelum = $rs->profitLoss('2024-01', '2025-12', $mid);

        // Voucher penutupan tiruan pada 2024-13: Dr Hasil / Cr Belanja (RM5,000)
        $js->post('2024-12-31', SourceType::JURNAL, null, 'Ujian penutupan',
            [
                ['coa_id' => $this->coaId($hasilKod), 'debit' => '5000.00', 'kredit' => '0.00'],
                ['coa_id' => $this->coaId($belanjaKod), 'debit' => '0.00', 'kredit' => '5000.00'],
            ],
            'YE-UJIAN-'.uniqid(), '2024-13', $mid);

        // Baki awal tiruan pada 2025-00 (Dr Hasil / Cr Belanja RM3,000)
        $js->post('2025-01-01', SourceType::OPENING, null, 'Ujian OB',
            [
                ['coa_id' => $this->coaId($hasilKod), 'debit' => '3000.00', 'kredit' => '0.00'],
                ['coa_id' => $this->coaId($belanjaKod), 'debit' => '0.00', 'kredit' => '3000.00'],
            ],
            'OB-UJIAN-'.uniqid(), '2025-00', $mid);

        $selepas = $rs->profitLoss('2024-01', '2025-12', $mid);

        // P&L julat merentas tahun TIDAK berubah — voucher '-13'/'-00' dikecualikan.
        $this->assertSame($sebelum['jumlah_hasil'], $selepas['jumlah_hasil']);
        $this->assertSame($sebelum['jumlah_belanja'], $selepas['jumlah_belanja']);
        $this->assertSame($sebelum['lebihan'], $selepas['lebihan']);
    }
}
