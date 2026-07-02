<?php

namespace App\Services\Laporan;

use Illuminate\Support\Facades\DB;

/**
 * Penyata Bulanan/Tahunan — ASAS TUNAI (buku tunai), format 2 lajur SPPKMS:
 *   KIRI : Baki Awal (B/B) per bank/PWR → Terimaan per kategori → Pelarasan Pindahan PWR
 *   KANAN: Perbelanjaan per kategori → Baki Akhir (B/H)
 *   JUMLAH kiri = JUMLAH kanan (mesti seimbang).
 *
 * PENTING (penemuan empirik): penyata/dashboard = TUNAI (termasuk beli aset
 * & rekupmen), BERBEZA dengan P&L (akruan, 600/650 sahaja).
 */
class StatementService
{
    /** Akaun tunai yang dipantau penyata (bank + tunai tangan + PWR). */
    private const KOD_TUNAI = ['250-05010', '250-05020', '250-05030', '250-06000', '250-06010', '250-06020', '250-06030'];

    /**
     * @param ?int $bankAccountId Jika diberi → penyata KHUSUS akaun bank itu
     *   (baki, terimaan, belanja, pindahan ditapis kepada bank tersebut sahaja).
     *   Null → penyata gabungan SEMUA akaun tunai (lalai).
     */
    public function monthly(string $periodYm, ?int $masjidId = null, ?int $bankAccountId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        // COA bank dipilih (untuk tapis baki tunai akaun itu sahaja)
        $bankCoaId = $bankAccountId
            ? (int) DB::table('bank_account')->where('id', $bankAccountId)->value('coa_id')
            : null;

        $bakiAwal  = $this->bakiTunaiSehingga($this->periodSebelum($periodYm), $masjidId, $bankCoaId);
        $bakiAkhir = $this->bakiTunaiSehingga($periodYm, $masjidId, $bankCoaId);

        // Terimaan per kategori COA (kutipan ACTIVE dalam bulan)
        $terimaan = DB::table('kutipan as k')
            ->join('coa as c', 'c.id', '=', 'k.coa_id')
            ->where('k.masjid_id', $masjidId)->where('k.status', 'ACTIVE')
            ->where('k.period_ym', $periodYm)
            ->when($bankAccountId, fn ($q) => $q->where('k.bank_account_id', $bankAccountId))
            ->groupBy('c.kod', 'c.nama')->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, ROUND(SUM(k.jumlah),2) as jumlah')
            ->get();

        // Perbelanjaan per kategori (bayaran + aset + PWR — TUNAI keluar; REKUPMEN diasingkan)
        $belanja = DB::table('pembayaran as p')
            ->join('coa as c', 'c.id', '=', 'p.coa_id')
            ->where('p.masjid_id', $masjidId)->where('p.status', 'ACTIVE')
            ->where('p.period_ym', $periodYm)
            ->whereIn('p.jenis', ['BAYARAN', 'ASET'])
            ->when($bankAccountId, fn ($q) => $q->where('p.bank_account_id', $bankAccountId))
            ->groupBy('c.kod', 'c.nama')->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, ROUND(SUM(p.jumlah),2) as jumlah')
            ->get();

        // Pelarasan Pindahan PWR (rekupmen) — KONTRA, bukan terimaan/belanja
        $pindahanPwr = DB::table('pembayaran as p')
            ->join('coa as c', 'c.id', '=', 'p.coa_id')
            ->where('p.masjid_id', $masjidId)->where('p.status', 'ACTIVE')
            ->where('p.period_ym', $periodYm)->where('p.jenis', 'REKUPMEN')
            ->when($bankAccountId, fn ($q) => $q->where('p.bank_account_id', $bankAccountId))
            ->groupBy('c.kod', 'c.nama')->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, ROUND(SUM(p.jumlah),2) as jumlah')
            ->get();

        $jumlahTerimaan = round((float) $terimaan->sum('jumlah'), 2);
        $jumlahBelanja  = round((float) $belanja->sum('jumlah'), 2);
        $jumlahBakiAwal  = round((float) collect($bakiAwal)->sum('baki'), 2);
        $jumlahBakiAkhir = round((float) collect($bakiAkhir)->sum('baki'), 2);
        $jumlahPindahan  = round((float) $pindahanPwr->sum('jumlah'), 2);

        /*
         | Pelarasan Pindahan PWR (rekupmen bank→PWR):
         |  - Penyata GABUNGAN (semua tunai): rekupmen NEUTRAL (bank↔PWR dalam kumpulan
         |    tunai) → papar di KEDUA-DUA sisi supaya kekal seimbang & kelihatan.
         |  - Penyata SATU BANK: rekupmen ialah aliran KELUAR bersih bank itu yang SUDAH
         |    diserap baki_akhir → papar di sisi KANAN sahaja (aliran keluar), JANGAN di kiri
         |    (jika tidak KIRI melebihi KANAN sebanyak jumlah rekupmen — pepijat B2).
         */
        $satuBank = $bankAccountId !== null;
        $jumlahKiri  = round($jumlahBakiAwal + $jumlahTerimaan + ($satuBank ? 0.0 : $jumlahPindahan), 2);
        $jumlahKanan = round($jumlahBelanja + $jumlahPindahan + $jumlahBakiAkhir, 2);

        return [
            'period'            => $periodYm,
            'satu_bank'         => $satuBank,
            'baki_awal'         => $bakiAwal,
            'jumlah_baki_awal'  => number_format($jumlahBakiAwal, 2, '.', ''),
            'terimaan'          => $terimaan,
            'jumlah_terimaan'   => number_format($jumlahTerimaan, 2, '.', ''),
            'pindahan_pwr'      => $pindahanPwr,
            'jumlah_pindahan'   => number_format($jumlahPindahan, 2, '.', ''),
            'belanja'           => $belanja,
            'jumlah_belanja'    => number_format($jumlahBelanja, 2, '.', ''),
            'baki_akhir'        => $bakiAkhir,
            'jumlah_baki_akhir' => number_format($jumlahBakiAkhir, 2, '.', ''),
            'jumlah_kiri'       => number_format($jumlahKiri, 2, '.', ''),
            'jumlah_kanan'      => number_format($jumlahKanan, 2, '.', ''),
        ];
    }

    /**
     * Penyata Tahunan 2-lajur (format SPPKMS penyataTN): Baki Awal (01/01) →
     * Terimaan tahun per COA → Pelarasan Pindahan PWR | Perbelanjaan tahun per COA
     * → Baki Akhir (31/12). JUMLAH kiri = JUMLAH kanan (seimbang). Asas tunai —
     * jumlah selari dengan agregat 12 penyata bulanan.
     */
    public function yearlyStatement(int $tahun, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');
        $dari = sprintf('%04d-01', $tahun);
        $hingga = sprintf('%04d-12', $tahun);

        $bakiAwal  = $this->bakiTunaiSehingga(sprintf('%04d-00', $tahun), $masjidId);
        $bakiAkhir = $this->bakiTunaiSehingga($hingga, $masjidId);

        $perKategori = function (string $jadual, string $statusKol, array $extra) use ($masjidId, $dari, $hingga) {
            $q = DB::table($jadual.' as x')->join('coa as c', 'c.id', '=', 'x.coa_id')
                ->where('x.masjid_id', $masjidId)->where('x.status', 'ACTIVE')
                ->whereBetween('x.period_ym', [$dari, $hingga]);
            foreach ($extra as $kol => $nilai) {
                is_array($nilai) ? $q->whereIn('x.'.$kol, $nilai) : $q->where('x.'.$kol, $nilai);
            }

            return $q->groupBy('c.kod', 'c.nama')->orderBy('c.kod')
                ->selectRaw('c.kod, c.nama, ROUND(SUM(x.jumlah),2) as jumlah')->get();
        };

        $terimaan    = $perKategori('kutipan', 'status', []);
        $belanja     = $perKategori('pembayaran', 'status', ['jenis' => ['BAYARAN', 'ASET']]);
        $pindahanPwr = $perKategori('pembayaran', 'status', ['jenis' => 'REKUPMEN']);

        $jTerimaan  = round((float) $terimaan->sum('jumlah'), 2);
        $jBelanja   = round((float) $belanja->sum('jumlah'), 2);
        $jBakiAwal  = round((float) collect($bakiAwal)->sum('baki'), 2);
        $jBakiAkhir = round((float) collect($bakiAkhir)->sum('baki'), 2);
        $jPindahan  = round((float) collect($pindahanPwr)->sum('jumlah'), 2);
        $jKiri  = round($jBakiAwal + $jTerimaan + $jPindahan, 2);
        $jKanan = round($jBelanja + $jBakiAkhir + $jPindahan, 2);

        return [
            'tahun'             => $tahun,
            'baki_awal'         => $bakiAwal,
            'jumlah_baki_awal'  => number_format($jBakiAwal, 2, '.', ''),
            'terimaan'          => $terimaan,
            'jumlah_terimaan'   => number_format($jTerimaan, 2, '.', ''),
            'pindahan_pwr'      => $pindahanPwr,
            'jumlah_pindahan'   => number_format($jPindahan, 2, '.', ''),
            'belanja'           => $belanja,
            'jumlah_belanja'    => number_format($jBelanja, 2, '.', ''),
            'baki_akhir'        => $bakiAkhir,
            'jumlah_baki_akhir' => number_format($jBakiAkhir, 2, '.', ''),
            'jumlah_kiri'       => number_format($jKiri, 2, '.', ''),
            'jumlah_kanan'      => number_format($jKanan, 2, '.', ''),
        ];
    }

    public function yearly(int $tahun, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');
        $bulanan = [];
        for ($b = 1; $b <= 12; $b++) {
            $ym = sprintf('%04d-%02d', $tahun, $b);
            $bulanan[$ym] = $this->ringkasanTunai($ym, $ym, $masjidId);
        }

        return [
            'tahun'   => $tahun,
            'bulanan' => $bulanan,
            'jumlah'  => $this->ringkasanTunai(sprintf('%04d-01', $tahun), sprintf('%04d-12', $tahun), $masjidId),
        ];
    }

    /** Ringkasan tunai (terima/bayar bank/bayar PWR/rekupmen) untuk julat period. */
    public function ringkasanTunai(string $dariYm, string $hinggaYm, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $terima = DB::table('kutipan')->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->whereBetween('period_ym', [$dariYm, $hinggaYm])->sum('jumlah');

        $bayarBank = DB::table('pembayaran')->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->whereBetween('period_ym', [$dariYm, $hinggaYm])
            ->where('cara_bayar', '<>', 'PWR')->whereIn('jenis', ['BAYARAN', 'ASET', 'REKUPMEN'])->sum('jumlah');

        $bayarPwr = DB::table('pembayaran')->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->whereBetween('period_ym', [$dariYm, $hinggaYm])
            ->where('cara_bayar', 'PWR')->sum('jumlah');

        return [
            'terima'      => number_format((float) $terima, 2, '.', ''),
            'bayar_bank'  => number_format((float) $bayarBank, 2, '.', ''),
            'bayar_pwr'   => number_format((float) $bayarPwr, 2, '.', ''),
            'bayar_tunai' => number_format((float) $bayarBank + (float) $bayarPwr, 2, '.', ''),
        ];
    }

    /** Penyata PWR — buku tunai satu akaun PWR untuk bulan dipilih. */
    public function pwrStatement(int $pwrCoaId, string $periodYm, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $base = DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->where('jv.status', 'POSTED')->where('jv.masjid_id', $masjidId)
            ->where('je.coa_id', $pwrCoaId);

        $bakiAwal = (float) (clone $base)->where('jv.period_ym', '<', $periodYm)
            ->selectRaw('COALESCE(SUM(je.debit - je.kredit),0) b')->value('b');

        $baris = (clone $base)->where('jv.period_ym', $periodYm)
            ->orderBy('jv.tarikh')->orderBy('jv.id')
            ->get(['jv.tarikh', 'jv.voucher_ref', 'jv.deskripsi', 'je.debit', 'je.kredit']);

        $berjalan = $bakiAwal;
        $baris = $baris->map(function ($r) use (&$berjalan) {
            $berjalan = round($berjalan + (float) $r->debit - (float) $r->kredit, 2);
            $r->baki = number_format($berjalan, 2, '.', '');

            return $r;
        });

        return [
            'baki_awal'  => number_format($bakiAwal, 2, '.', ''),
            'baris'      => $baris,
            'baki_akhir' => number_format($berjalan, 2, '.', ''),
        ];
    }

    /**
     * Baki setiap akaun tunai (bank/PWR) sehingga period (termasuk).
     * @param ?int $bankCoaId Jika diberi → baki AKAUN BANK itu sahaja (penyata ikut bank).
     */
    public function bakiTunaiSehingga(string $hinggaYm, ?int $masjidId = null, ?int $bankCoaId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $rows = DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->where('jv.status', 'POSTED')->where('jv.masjid_id', $masjidId)
            ->where('jv.period_ym', '<=', $hinggaYm)
            ->when($bankCoaId, fn ($q) => $q->where('je.coa_id', $bankCoaId))
            ->when(!$bankCoaId, fn ($q) => $q->whereIn('c.kod', self::KOD_TUNAI))
            ->groupBy('c.kod', 'c.nama')->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, ROUND(SUM(je.debit - je.kredit),2) as baki')
            ->get();

        // Penyata ikut bank: pulang baki akaun itu sahaja (walau 0)
        if ($bankCoaId) {
            if ($rows->isNotEmpty()) {
                return $rows->all();
            }
            $coa = DB::table('coa')->where('id', $bankCoaId)->first(['kod', 'nama']);

            return $coa ? [(object) ['kod' => $coa->kod, 'nama' => $coa->nama, 'baki' => '0.00']] : [];
        }

        // Penyata gabungan: pastikan semua akaun tunai dipapar walau baki 0
        return collect(self::KOD_TUNAI)->map(function ($kod) use ($rows, $masjidId) {
            $r = $rows->firstWhere('kod', $kod);
            if ($r) {
                return $r;
            }
            $nama = DB::table('coa')->where('masjid_id', $masjidId)->where('kod', $kod)->value('nama');

            return $nama ? (object) ['kod' => $kod, 'nama' => $nama, 'baki' => '0.00'] : null;
        })->filter()->values()->all();
    }

    private function periodSebelum(string $periodYm): string
    {
        [$y, $m] = explode('-', $periodYm);
        if ($m === '01') {
            // '-00' = baki pembukaan tahun (konvensyen migrasi) — termasuk dalam baki awal Januari
            return $y.'-00';
        }

        return sprintf('%04d-%02d', (int) $y, (int) $m - 1);
    }
}
