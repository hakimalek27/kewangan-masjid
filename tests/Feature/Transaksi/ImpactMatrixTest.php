<?php

namespace Tests\Feature\Transaksi;

use App\Models\BankAccount;
use App\Models\FixedAsset;
use App\Models\KutipanDenominasi;
use App\Services\Transaksi\BelanjaJurnalService;
use App\Services\Transaksi\FdService;
use App\Services\Transaksi\KutipanService;
use App\Services\Transaksi\PembayaranService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * REPLIKASI UJIAN EMPIRIK (LAPORAN-IMPAK-EMPIRIK.md seksyen 2):
 * baseline → cipta RM1.00 → sahkan delta TEPAT ikut matriks → VOID →
 * residual KOSONG. Setiap jenis transaksi diuji.
 */
class ImpactMatrixTest extends TestCase
{
    use DatabaseTransactions, MasjidContext;

    private KutipanService $kutipan;
    private PembayaranService $pembayaran;
    private BelanjaJurnalService $jurnal;
    private FdService $fd;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
        $this->kutipan = app(KutipanService::class);
        $this->pembayaran = app(PembayaranService::class);
        $this->jurnal = app(BelanjaJurnalService::class);
        $this->fd = app(FdService::class);
        $this->bank = BankAccount::withoutMasjidScope()->where('masjid_id', config('sppkms.masjid_id'))->firstOrFail();
    }

    /** Snapshot metrik audit — dikira terus dari jurnal (POSTED sahaja), seperti laporan. */
    private function snapshot(): array
    {
        $base = fn () => DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->where('jv.status', 'POSTED')
            ->where('jv.masjid_id', config('sppkms.masjid_id'));

        // Jumlah Imbangan Duga = Σ baki sebelah debit (= Σ sebelah kredit)
        $tb = DB::selectOne('
            SELECT ROUND(SUM(GREATEST(b,0)),2) AS total FROM (
                SELECT SUM(je.debit - je.kredit) AS b
                FROM journal_entry je
                JOIN journal_voucher jv ON jv.id = je.voucher_id
                WHERE jv.status="POSTED" AND jv.masjid_id = ?
                GROUP BY je.coa_id
            ) x', [config('sppkms.masjid_id')])->total;

        return [
            'bank'    => $this->bakiCoa('250-05010'),
            'tunai'   => $this->bakiCoa('250-06000'),
            'pwr'     => $this->bakiCoa('250-06010'),
            'fd'      => $this->bakiCoa('250-04010'),
            'aset_it' => $this->bakiCoa('200-01060'),
            'snt'     => $this->bakiCoa('200-01065'),
            'tb'      => number_format((float) $tb, 2, '.', ''),
            'hasil'   => number_format((float) (clone $base())->where('c.jenis', 'Hasil')->selectRaw('SUM(je.kredit - je.debit) s')->value('s'), 2, '.', ''),
            'belanja' => number_format((float) (clone $base())->where('c.jenis', 'Belanja')->selectRaw('SUM(je.debit - je.kredit) s')->value('s'), 2, '.', ''),
        ];
    }

    private function assertDelta(array $sebelum, array $selepas, array $jangkaan, string $kes): void
    {
        foreach ($sebelum as $k => $v) {
            $delta = bcsub($selepas[$k], $v, 2);
            $patut = number_format((float) ($jangkaan[$k] ?? 0), 2, '.', '');
            $this->assertSame($patut, $delta, "[$kes] metrik '$k': delta $delta, sepatutnya $patut");
        }
    }

    private function assertResidualKosong(array $baseline, string $kes): void
    {
        $this->assertDelta($baseline, $this->snapshot(), [], "$kes — RESIDUAL");
    }

    // ============ (1) KUTIPAN BANK: Dr Bank / Cr Hasil ============
    public function test_kutipan_bank(): void
    {
        $b = $this->snapshot();

        $k = $this->kutipan->create([
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'BANK_TRANSFER_QR', 'jumlah' => '1.00',
            'no_resit' => 'UJI-M1', 'bank_account_id' => $this->bank->id,
            'deskripsi' => 'UJIAN MATRIKS 1',
        ]);

        $this->assertDelta($b, $this->snapshot(), ['bank' => 1, 'hasil' => 1, 'tb' => 1], 'KUTIPAN BANK');

        $this->kutipan->void($k->fresh(), 'ujian');
        $this->assertResidualKosong($b, 'KUTIPAN BANK');
    }

    // ============ (2) KUTIPAN TUNAI: Dr Tunai Di Tangan / Cr Hasil ============
    public function test_kutipan_tunai(): void
    {
        $b = $this->snapshot();

        $k = $this->kutipan->create([
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'TUNAI', 'jumlah' => '1.00', 'no_resit' => 'UJI-M2',
        ]);

        $this->assertDelta($b, $this->snapshot(), ['tunai' => 1, 'hasil' => 1, 'tb' => 1], 'KUTIPAN TUNAI');

        $this->kutipan->void($k->fresh(), 'ujian');
        $this->assertResidualKosong($b, 'KUTIPAN TUNAI');
    }

    // ============ (3) KUTIPAN TABUNG: denominasi → Dr Bank / Cr 400-01020 ============
    public function test_kutipan_tabung_jumaat_denominasi(): void
    {
        $b = $this->snapshot();

        $k = $this->kutipan->createTabung([
            'tarikh' => '2026-06-12', 'kaedah' => 'TUNAI', 'jenis_tabung' => 'JUMAAT',
            'no_resit' => 'UJI-M3', 'deskripsi' => 'UJIAN TABUNG',
        ], [
            ['denominasi' => '100.00', 'bilangan' => 5],   // 500.00
            ['denominasi' => '0.50', 'bilangan' => 3],     //   1.50
            ['denominasi' => '0.01', 'bilangan' => 7],     //   0.07
        ]);

        $this->assertSame('501.57', (string) $k->jumlah);
        $this->assertSame(3, KutipanDenominasi::where('kutipan_id', $k->id)->count());
        $this->assertDelta($b, $this->snapshot(), ['tunai' => 501.57, 'hasil' => 501.57, 'tb' => 501.57], 'TABUNG');

        $this->kutipan->void($k->fresh(), 'ujian');
        $this->assertResidualKosong($b, 'TABUNG');
    }

    // ============ (4) BAYARAN EFT: Dr Belanja / Cr Bank — TB TIDAK berubah ============
    public function test_bayaran_eft(): void
    {
        $b = $this->snapshot();

        $p = $this->pembayaran->createBayaran([
            'tar_lulus' => '2026-06-12', 'coa_id' => $this->coaId('600-06000'),
            'jumlah' => '1.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'baucer_no' => 'UJI-M4', 'pemohon' => 'UJIAN', 'deskripsi' => 'UJIAN MATRIKS 4',
        ]);

        $this->assertDelta($b, $this->snapshot(), ['bank' => -1, 'belanja' => 1, 'tb' => 0], 'BAYARAN EFT');

        $this->pembayaran->void($p->fresh(), 'ujian');
        $this->assertResidualKosong($b, 'BAYARAN EFT');
    }

    // ============ (5) BAYARAN PWR: Dr Belanja / Cr PWR ============
    public function test_bayaran_pwr(): void
    {
        $b = $this->snapshot();

        $p = $this->pembayaran->createBayaran([
            'tar_lulus' => '2026-06-12', 'coa_id' => $this->coaId('600-01000'),
            'jumlah' => '1.00', 'cara_bayar' => 'PWR',
            'pwr_coa_id' => $this->coaId('250-06010'),
            'baucer_no' => 'UJI-M5', 'deskripsi' => 'UJIAN MATRIKS 5',
        ]);

        $this->assertDelta($b, $this->snapshot(), ['pwr' => -1, 'belanja' => 1, 'tb' => 0], 'BAYARAN PWR');

        $this->pembayaran->void($p->fresh(), 'ujian');
        $this->assertResidualKosong($b, 'BAYARAN PWR');
    }

    // ============ (6) BELI ASET: Dr Aset / Cr Bank — BUKAN P&L + daftar aset ============
    public function test_beli_aset(): void
    {
        $b = $this->snapshot();

        $p = $this->pembayaran->createAset([
            'tar_lulus' => '2026-06-12', 'coa_id' => $this->coaId('200-01060'),
            'jumlah' => '1.00', 'cara_bayar' => 'EFT', 'bank_account_id' => $this->bank->id,
            'baucer_no' => 'UJI-M6', 'asset_name' => 'UJIAN ASET MATRIKS',
            'asset_location' => 'PEJABAT', 'useful_life' => 5, 'depn_rate' => 20,
        ]);

        $this->assertDelta($b, $this->snapshot(), ['aset_it' => 1, 'bank' => -1, 'belanja' => 0, 'hasil' => 0, 'tb' => 0], 'BELI ASET');

        $aset = FixedAsset::withoutMasjidScope()->where('pembayaran_id', $p->id)->first();
        $this->assertNotNull($aset, 'Aset mesti didaftar serentak');
        $this->assertSame('AKTIF', $aset->status);
        $this->assertSame($this->coaId('200-01065'), (int) $aset->snt_coa_id, 'SNT auto-padan');

        // VOID → aset turut DIPADAM (pembaikan quirk sistem lama)
        $this->pembayaran->void($p->fresh(), 'ujian');
        $this->assertResidualKosong($b, 'BELI ASET');
        $this->assertSame('DIPADAM', $aset->fresh()->status);
    }

    // ============ (7) BELANJA JURNAL: Dr 650 / Cr SNT — TB +1, tiada tunai ============
    public function test_belanja_jurnal_susut_nilai(): void
    {
        $b = $this->snapshot();

        $v = $this->jurnal->create('2026-06-12', $this->coaId('650-10000'), $this->coaId('200-01065'), '1.00', 'UJIAN SUSUT NILAI');

        $this->assertDelta($b, $this->snapshot(), ['belanja' => 1, 'snt' => -1, 'bank' => 0, 'tb' => 1], 'BELANJA JURNAL');
        $this->assertStringStartsWith('JNL', $v->voucher_ref);

        app(\App\Services\Accounting\VoidService::class)->voidVoucher($v, 'ujian');
        $this->assertResidualKosong($b, 'BELANJA JURNAL');
    }

    // ============ (8) REKUPMEN PWR: Dr PWR / Cr Bank — PEMINDAHAN, bukan P&L ============
    public function test_rekupmen_pwr(): void
    {
        $b = $this->snapshot();

        $p = $this->pembayaran->createRekupmen([
            'tar_lulus' => '2026-06-12', 'pwr_coa_id' => $this->coaId('250-06010'),
            'bank_account_id' => $this->bank->id, 'jumlah' => '1.00',
            'baucer_no' => 'UJI-M8', 'deskripsi' => 'UJIAN REKUPMEN',
        ]);

        $this->assertDelta($b, $this->snapshot(), ['pwr' => 1, 'bank' => -1, 'belanja' => 0, 'hasil' => 0, 'tb' => 0], 'REKUPMEN');
        $this->assertSame('REKUPMEN', $p->jenis);

        $this->pembayaran->void($p->fresh(), 'ujian');
        $this->assertResidualKosong($b, 'REKUPMEN');
    }

    // ============ (9) FD BARU: Dr FD / Cr Bank — pemindahan aset ============
    public function test_fd_baru_dan_matang(): void
    {
        $b = $this->snapshot();

        $fd = $this->fd->create([
            'tarikh' => '2026-06-12', 'institusi' => 'BANK UJIAN',
            'coa_fd_id' => $this->coaId('250-04010'), 'coa_bank_id' => $this->coaId('250-05010'),
            'jumlah' => '1.00', 'no_sijil' => 'UJI-FD-1',
        ]);

        $this->assertDelta($b, $this->snapshot(), ['fd' => 1, 'bank' => -1, 'belanja' => 0, 'hasil' => 0, 'tb' => 0], 'FD BARU');

        // Matang: wang kembali — Dr Bank / Cr FD → semua pulih
        $this->fd->mature($fd, '2026-06-12');
        $this->assertResidualKosong($b, 'FD MATANG');
        $this->assertSame('MATANG', $fd->fresh()->status);
    }

    // ============ (10) DIVIDEN FD: Dr Bank / Cr 450 + fd_dividend; padam FD tak sentuh dividen ============
    public function test_dividen_fd(): void
    {
        $fd = $this->fd->create([
            'tarikh' => '2026-06-12', 'institusi' => 'BANK UJIAN DIV',
            'coa_fd_id' => $this->coaId('250-04010'), 'coa_bank_id' => $this->coaId('250-05010'),
            'jumlah' => '100.00', 'no_sijil' => 'UJI-FD-2',
        ]);

        $b = $this->snapshot();

        $k = $this->kutipan->createDividen($fd, [
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('450-01000'),
            'jumlah' => '1.00', 'no_resit' => 'UJI-M10', 'bank_account_id' => $this->bank->id,
        ]);

        $this->assertDelta($b, $this->snapshot(), ['bank' => 1, 'hasil' => 1, 'tb' => 1], 'DIVIDEN');
        $this->assertDatabaseHas('fd_dividend', ['fd_id' => $fd->id, 'kutipan_id' => $k->id]);

        // Padam dividen melalui kutipan → fd_dividend turut hilang, residual kosong
        $this->kutipan->void($k->fresh(), 'ujian');
        $this->assertDatabaseMissing('fd_dividend', ['kutipan_id' => $k->id]);
        $this->assertResidualKosong($b, 'DIVIDEN');

        // Padam FD TIDAK memadam rekod dividen/kutipan (tingkah laku SPPKMS dikekalkan)
        $k2 = $this->kutipan->createDividen($fd->fresh(), [
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('450-01000'),
            'jumlah' => '1.00', 'no_resit' => 'UJI-M10B', 'bank_account_id' => $this->bank->id,
        ]);
        $this->fd->void($fd->fresh(), 'ujian');
        $this->assertSame('ACTIVE', $k2->fresh()->status, 'Dividen kekal walaupun FD dipadam');
    }

    // ============ Nombor auto vs manual ============
    public function test_nombor_manual_tidak_menambah_kaunter(): void
    {
        $noSebelum = DB::table('number_sequence')->where('masjid_id', config('sppkms.masjid_id'))->where('jenis', 'RESIT')->value('next_no');

        $this->kutipan->create([
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'TUNAI', 'jumlah' => '1.00', 'no_resit' => 'MANUAL-99',
        ]);

        $noSelepas = DB::table('number_sequence')->where('masjid_id', config('sppkms.masjid_id'))->where('jenis', 'RESIT')->value('next_no');
        $this->assertSame($noSebelum, $noSelepas, 'Kaunter RESIT tidak patut berubah untuk nombor manual');
    }

    public function test_nombor_auto_menambah_kaunter(): void
    {
        $noSebelum = (int) DB::table('number_sequence')->where('masjid_id', config('sppkms.masjid_id'))->where('jenis', 'RESIT')->value('next_no');

        $k = $this->kutipan->create([
            'tarikh' => '2026-06-12', 'coa_id' => $this->coaId('400-03010'),
            'kaedah' => 'TUNAI', 'jumlah' => '1.00', 'auto_resit' => true,
        ]);

        $this->assertSame(str_pad((string) $noSebelum, 4, '0', STR_PAD_LEFT), $k->no_resit);
        $this->assertSame($noSebelum + 1, (int) DB::table('number_sequence')->where('masjid_id', config('sppkms.masjid_id'))->where('jenis', 'RESIT')->value('next_no'));
    }
}
