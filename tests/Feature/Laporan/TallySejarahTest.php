<?php

namespace Tests\Feature\Laporan;

use App\Services\Laporan\ReportService;
use App\Services\Laporan\StatementService;
use Tests\Concerns\MasjidContext;
use Tests\TestCase;

/**
 * UJIAN TALLY DATA SEJARAH — laporan sistem baharu MESTI sama KE SEN
 * dengan angka yang disahkan tally 100% lawan SPPKMS/V1:
 *   - shots/PENYATA-PENDAPATAN.md (30 bulan + tahunan + rekonsiliasi tunai)
 *   - shots/KUNCI-KIRA-KIRA.md (BS @2026-06)
 *   - MIGRASI-SUMMARY.md (laporan program)
 * SATU SEN pun tidak boleh lari — sistem audit.
 *
 * NOTA REKALIBRASI (8 Jul 2026): resit duplikat 1851 (RM8,566.00, 2025-08)
 * di-VOID dalam produksi 15 Jun (SEJARAH-KERJA §7k — rekonsiliasi bank sahkan
 * Ogos 2025 = 52,581.98 = penyata bank tepat). Angka 2025-08, tahunan 2025 &
 * BS @2026-06 di bawah = nilai SELEPAS void (setia produksi `spkm`); nilai
 * lama (+8,566) hanya wujud dalam klon ujian basi pra-void.
 */
class TallySejarahTest extends TestCase
{
    use MasjidContext;

    /** [period => [pendapatan, perbelanjaan, lebihan]] — shots/PENYATA-PENDAPATAN.md */
    private const PNL_BULANAN = [
        '2024-01' => ['78264.55', '94950.90', '-16686.35'],
        '2024-02' => ['85814.72', '54229.00', '31585.72'],
        '2024-03' => ['182145.99', '121998.05', '60147.94'],
        '2024-04' => ['91783.23', '186924.09', '-95140.86'],
        '2024-05' => ['82596.23', '63976.65', '18619.58'],
        '2024-06' => ['54333.47', '103482.40', '-49148.93'],
        '2024-07' => ['44715.14', '59334.56', '-14619.42'],
        '2024-08' => ['60837.27', '43318.15', '17519.12'],
        '2024-09' => ['55715.26', '56300.13', '-584.87'],
        '2024-10' => ['113029.32', '50481.43', '62547.89'],
        '2024-11' => ['52100.10', '54384.45', '-2284.35'],
        '2024-12' => ['53671.19', '38340.95', '15330.24'],
        '2025-01' => ['71127.91', '44339.40', '26788.51'],
        '2025-02' => ['92912.48', '52799.40', '40113.08'],
        '2025-03' => ['157508.16', '188269.65', '-30761.49'],
        '2025-04' => ['65702.01', '61967.10', '3734.91'],
        '2025-05' => ['118520.22', '83401.40', '35118.82'],
        '2025-06' => ['59872.39', '103980.39', '-44108.00'],
        '2025-07' => ['57347.58', '58628.58', '-1281.00'],
        '2025-08' => ['52581.98', '48215.85', '4366.13'],
        '2025-09' => ['55255.99', '79910.36', '-24654.37'],
        '2025-10' => ['73359.08', '44492.57', '28866.51'],
        '2025-11' => ['43825.86', '59100.12', '-15274.26'],
        '2025-12' => ['62865.23', '57790.03', '5075.20'],
        '2026-01' => ['80712.78', '47558.78', '33154.00'],
        '2026-02' => ['131197.45', '100794.65', '30402.80'],
        '2026-03' => ['127886.94', '192577.69', '-64690.75'],
        '2026-04' => ['73610.00', '23394.56', '50215.44'],
        '2026-05' => ['17385.00', '61473.81', '-44088.81'],
        '2026-06' => ['1499.00', '0.00', '1499.00'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->aktifkanKonteksMasjid();
    }

    public function test_penyata_pendapatan_30_bulan_tally_ke_sen(): void
    {
        $report = app(ReportService::class);
        $gagal = [];

        foreach (self::PNL_BULANAN as $ym => [$pendapatan, $belanja, $lebihan]) {
            $pl = $report->profitLoss($ym, $ym);

            if ($pl['jumlah_hasil'] !== $pendapatan) {
                $gagal[] = "$ym pendapatan: {$pl['jumlah_hasil']} ≠ $pendapatan";
            }
            if ($pl['jumlah_belanja'] !== $belanja) {
                $gagal[] = "$ym belanja: {$pl['jumlah_belanja']} ≠ $belanja";
            }
            if ($pl['lebihan'] !== $lebihan) {
                $gagal[] = "$ym lebihan: {$pl['lebihan']} ≠ $lebihan";
            }
        }

        $this->assertSame([], $gagal, "TALLY GAGAL:\n".implode("\n", $gagal));
        $this->assertCount(30, self::PNL_BULANAN);
    }

    public function test_ringkasan_tahunan_tally(): void
    {
        $report = app(ReportService::class);

        $jangkaan = [
            2024 => ['955006.47', '927720.76', '27285.71'],
            2025 => ['910878.89', '882894.85', '27984.04'],
            2026 => ['432291.17', '425799.49', '6491.68'],
        ];

        foreach ($jangkaan as $tahun => [$p, $b, $l]) {
            $pl = $report->profitLoss("$tahun-01", "$tahun-12");
            $this->assertSame($p, $pl['jumlah_hasil'], "$tahun pendapatan");
            $this->assertSame($b, $pl['jumlah_belanja'], "$tahun belanja");
            $this->assertSame($l, $pl['lebihan'], "$tahun lebihan");
        }
    }

    public function test_kunci_kira_kira_jun_2026_tally(): void
    {
        $bs = app(ReportService::class)->balanceSheet('2026-06');

        $this->assertSame('174589.95', $bs['total_aset'], 'Total Aset');
        $this->assertSame('-12565.00', $bs['total_liabiliti'], 'Total Liabiliti');
        $this->assertSame('187154.95', $bs['total_ekuiti'], 'Total Ekuiti');
        $this->assertSame('61761.43', $bs['lebihan_terkumpul'], 'Lebihan Terkumpul 2024-2026');
        $this->assertTrue($bs['seimbang'], 'Kunci Kira-Kira mesti SEIMBANG');

        // Baris individu (shots/KUNCI-KIRA-KIRA.md)
        $cari = fn ($senarai, $kod) => collect($senarai)->firstWhere('kod', $kod)?->amaun;
        $this->assertSame('877.00', $cari($bs['aset'], '200-01050'));
        $this->assertSame('2507.00', $cari($bs['aset'], '200-01060'));
        $this->assertSame('165192.17', $cari($bs['aset'], '250-05010'), 'Bank Akaun 1');
        $this->assertSame('1953.78', $cari($bs['aset'], '250-06010'), 'PWR Masjid');
        $this->assertSame('4060.00', $cari($bs['aset'], '250-06030'), 'PWR Rahmah Madani');
        $this->assertSame('895.00', $cari($bs['liabiliti'], '300-04020'), 'Asnaf');
        $this->assertSame('250.00', $cari($bs['liabiliti'], '300-04040'), 'Pembangunan');
        $this->assertSame('-23850.00', $cari($bs['liabiliti'], '300-04050'), 'Rahmah Madani (overdrawn — remark pengguna)');
        $this->assertSame('10140.00', $cari($bs['liabiliti'], '300-99990'), 'Akaun Sementara (suspense — remark)');
        $this->assertSame('125393.52', $cari($bs['ekuiti'], '100-10000'), 'Dana Terkumpul');
    }

    public function test_imbangan_duga_seimbang(): void
    {
        $tb = app(ReportService::class)->trialBalance('2026-06');

        $this->assertSame($tb['jumlah_debit'], $tb['jumlah_kredit'], 'Imbangan Duga mesti Dr = Cr');
        $this->assertGreaterThan(0, (float) $tb['jumlah_debit']);
    }

    public function test_rekonsiliasi_tunai_tahunan_tally(): void
    {
        $stmt = app(StatementService::class);

        // shots/PENYATA-PENDAPATAN.md — Terima Tunai / Bayar Tunai (buku tunai)
        $jangkaan = [
            2024 => ['959433.47', '941085.61'],
            2025 => ['919449.89', '899569.50'],
            2026 => ['433748.17', '438318.49'],
        ];

        foreach ($jangkaan as $tahun => [$terima, $bayar]) {
            $r = $stmt->ringkasanTunai("$tahun-01", "$tahun-12");
            $this->assertSame($terima, $r['terima'], "$tahun terima tunai");
            $this->assertSame($bayar, $r['bayar_tunai'], "$tahun bayar tunai");
        }
    }

    public function test_laporan_program_tally(): void
    {
        $program = app(ReportService::class)->programReport();

        // MIGRASI-SUMMARY.md: 82 program; nilai utama disahkan
        $this->assertGreaterThanOrEqual(80, $program->count());

        $cari = fn ($nama) => $program->firstWhere('program', $nama);
        $this->assertSame('163187.39', $cari('IHYA RAMADAN')?->net, 'IHYA RAMADAN net');
        $this->assertSame('50901.73', $cari('QURBAN')?->net, 'QURBAN net');
        $this->assertSame('-3077.20', $cari('BOWLING')?->net, 'BOWLING net');
        $this->assertSame('-38093.60', $cari('KELAS TALAQQI')?->net, 'KELAS TALAQQI net');
    }

    public function test_penyata_bulanan_seimbang_setiap_bulan(): void
    {
        $stmt = app(StatementService::class);

        foreach (array_keys(self::PNL_BULANAN) as $ym) {
            $p = $stmt->monthly($ym);

            // Identiti buku tunai: BakiAwal + Terimaan − (Belanja TUNAI bank+PWR... )
            // Semakan minimum di sini: baki akhir = baki awal + aliran jurnal tunai bulan itu;
            // kedua-duanya dikira dari jurnal jadi semak identiti kiri=kanan penyata:
            $kiri  = bcadd($p['jumlah_baki_awal'], $p['jumlah_terimaan'], 2);
            $kanan = bcadd($p['jumlah_belanja'], $p['jumlah_baki_akhir'], 2);

            $this->assertSame($kiri, $kanan, "Penyata $ym tidak seimbang: kiri $kiri ≠ kanan $kanan");
        }
    }
}
