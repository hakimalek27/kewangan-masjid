<?php

namespace Tests\Feature\Laporan;

use App\Models\BankAccount;
use App\Services\Laporan\StatementService;
use App\Services\Transaksi\KutipanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * Penyata Ikut Bank MESTI menapis data per akaun bank (bukan hanya tukar tajuk) —
 * penemuan audit #2/#6. Membuktikan dua bank menghasilkan angka BERBEZA.
 */
class PenyataBankTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    public function test_penyata_ikut_bank_tapis_per_akaun(): void
    {
        $mid = config('spkm.masjid_id');
        $bankA = BankAccount::withoutMasjidScope()->where('masjid_id', $mid)->firstOrFail();

        $bankB = BankAccount::withoutMasjidScope()->create([
            'masjid_id' => $mid, 'slot' => 2, 'nama_bank' => 'BANK UJIAN 2',
            'no_akaun' => '222222', 'coa_id' => $this->coaId('250-05020'),
            'status' => 'AKTIF', 'digunakan' => 1,
        ]);

        $ym = '2026-06';
        $kutipan = app(KutipanService::class);

        // Bank A terima RM111 (period 2026-06)
        $kutipan->create([
            'tarikh' => '2026-06-15', 'period_ym' => $ym, 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'BANK_TRANSFER_QR', 'jumlah' => '111.00', 'no_resit' => 'PB-A',
            'bank_account_id' => $bankA->id,
        ]);
        // Bank B terima RM222
        $kutipan->create([
            'tarikh' => '2026-06-15', 'period_ym' => $ym, 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'BANK_TRANSFER_QR', 'jumlah' => '222.00', 'no_resit' => 'PB-B',
            'bank_account_id' => $bankB->id,
        ]);

        $svc = app(StatementService::class);
        $penyataA = $svc->monthly($ym, $mid, $bankA->id);
        $penyataB = $svc->monthly($ym, $mid, $bankB->id);
        $gabung   = $svc->monthly($ym, $mid); // tiada bank → semua

        // Terimaan bank A & B mesti BERBEZA (bukan sama seperti bug lama)
        $this->assertNotSame($penyataA['jumlah_terimaan'], $penyataB['jumlah_terimaan'],
            'Penyata ikut bank mesti tapis per akaun — angka tidak boleh sama.');

        // Bank B terimaan termasuk RM222 (sekurang-kurangnya), bukan RM111
        $this->assertGreaterThanOrEqual(222.00, (float) $penyataB['jumlah_terimaan']);
        $this->assertGreaterThanOrEqual(111.00, (float) $penyataA['jumlah_terimaan']);

        // Gabungan >= mana-mana bank tunggal
        $this->assertGreaterThanOrEqual((float) $penyataA['jumlah_terimaan'], (float) $gabung['jumlah_terimaan']);

        // Baki ikut bank hanya papar akaun itu (1 baris), gabungan papar semua (7)
        $this->assertCount(1, $penyataB['baki_akhir']);
        $this->assertSame('250-05020', $penyataB['baki_akhir'][0]->kod);
    }

    /**
     * B2 — penyata SATU BANK bulan ber-REKUPMEN mesti SEIMBANG (kiri = kanan).
     * Sebelum fix: rekupmen ditambah dua sisi → kiri > kanan sebanyak jumlah rekupmen.
     */
    public function test_penyata_ikut_bank_seimbang_walau_ada_rekupmen(): void
    {
        $mid = config('spkm.masjid_id');
        $bankA = BankAccount::withoutMasjidScope()->where('masjid_id', $mid)
            ->where('coa_id', $this->coaId('250-05010'))->firstOrFail();

        $svc = app(StatementService::class);

        // 2024-01: data sejarah ada rekupmen RM944.90 dari bank AFFIN (250-05010)
        $bank = $svc->monthly('2024-01', $mid, $bankA->id);
        $gabung = $svc->monthly('2024-01', $mid);

        $this->assertTrue($bank['satu_bank']);
        $this->assertGreaterThan(0, (float) $bank['jumlah_pindahan'], 'Prasyarat: bulan ini ada rekupmen.');

        // Penyata SATU BANK seimbang
        $this->assertSame($bank['jumlah_kiri'], $bank['jumlah_kanan'],
            'Penyata ikut bank mesti seimbang walau ada rekupmen (B2).');
        // Kiri satu-bank TIDAK termasuk pindahan; kanan termasuk pindahan
        $this->assertSame(
            number_format((float) $bank['jumlah_baki_awal'] + (float) $bank['jumlah_terimaan'], 2, '.', ''),
            $bank['jumlah_kiri']
        );

        // Penyata GABUNGAN juga seimbang (pindahan dua sisi — kekal betul)
        $this->assertFalse($gabung['satu_bank']);
        $this->assertSame($gabung['jumlah_kiri'], $gabung['jumlah_kanan']);
    }
}
