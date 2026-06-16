<?php

namespace App\Services\Laporan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan perakaunan — SEMUA dikira real-time daripada journal_entry
 * (voucher status POSTED sahaja). Pengelompokan bulanan menggunakan
 * period_ym ('YYYY-00' = baki pembukaan tahun, terletak sebelum '-01').
 *
 *   Hasil   = Σ(kredit − debit) untuk coa.jenis='Hasil'   (400/450)
 *   Belanja = Σ(debit − kredit) untuk coa.jenis='Belanja' (600/650)
 *   Lebihan = Hasil − Belanja
 */
class ReportService
{
    private function base(?int $masjidId = null)
    {
        $masjidId ??= app('current.masjid_id');

        return DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->where('jv.status', 'POSTED')
            ->where('jv.masjid_id', $masjidId);
    }

    /** Imbangan Duga pada cutoff (period_ym <= $hinggaYm). Σ Debit mesti = Σ Kredit. */
    public function trialBalance(string $hinggaYm, ?int $masjidId = null): array
    {
        $baris = $this->base($masjidId)
            ->where('jv.period_ym', '<=', $hinggaYm)
            ->groupBy('c.id', 'c.kod', 'c.nama')
            ->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, ROUND(SUM(je.debit - je.kredit),2) as baki')
            ->get()
            ->filter(fn ($r) => (float) $r->baki != 0.0)
            ->map(fn ($r) => (object) [
                'kod'    => $r->kod,
                'nama'   => $r->nama,
                'debit'  => $r->baki > 0 ? number_format((float) $r->baki, 2, '.', '') : '0.00',
                'kredit' => $r->baki < 0 ? number_format(-(float) $r->baki, 2, '.', '') : '0.00',
            ])
            ->values();

        return [
            'baris'        => $baris,
            'jumlah_debit'  => number_format($baris->sum(fn ($r) => (float) $r->debit), 2, '.', ''),
            'jumlah_kredit' => number_format($baris->sum(fn ($r) => (float) $r->kredit), 2, '.', ''),
        ];
    }

    /** Untung Rugi (akruan) untuk julat period_ym [dari, hingga]. */
    public function profitLoss(string $dariYm, string $hinggaYm, ?int $masjidId = null): array
    {
        $rows = $this->base($masjidId)
            ->whereBetween('jv.period_ym', [$dariYm, $hinggaYm])
            ->whereIn('c.jenis', ['Hasil', 'Belanja'])
            ->groupBy('c.id', 'c.kod', 'c.nama', 'c.jenis')
            ->orderBy('c.kod')
            ->selectRaw("c.kod, c.nama, c.jenis,
                ROUND(SUM(CASE WHEN c.jenis='Hasil' THEN je.kredit - je.debit ELSE je.debit - je.kredit END),2) as amaun")
            ->get();

        $hasil   = $rows->where('jenis', 'Hasil')->values();
        $belanja = $rows->where('jenis', 'Belanja')->values();
        $jumlahHasil   = round((float) $hasil->sum('amaun'), 2);
        $jumlahBelanja = round((float) $belanja->sum('amaun'), 2);

        return [
            'hasil'           => $hasil,
            'belanja'         => $belanja,
            'jumlah_hasil'    => number_format($jumlahHasil, 2, '.', ''),
            'jumlah_belanja'  => number_format($jumlahBelanja, 2, '.', ''),
            'lebihan'         => number_format($jumlahHasil - $jumlahBelanja, 2, '.', ''),
        ];
    }

    /**
     * Kunci Kira-Kira pada cutoff. Ekuiti = akaun 100 + Lebihan Terkumpul
     * (Hasil−Belanja semua tempoh ≤ cutoff). Mesti SEIMBANG:
     * Total Aset = Total Liabiliti + Total Ekuiti.
     */
    public function balanceSheet(string $hinggaYm, ?int $masjidId = null): array
    {
        $rows = $this->base($masjidId)
            ->where('jv.period_ym', '<=', $hinggaYm)
            ->groupBy('c.id', 'c.kod', 'c.nama', 'c.jenis')
            ->orderBy('c.kod')
            ->selectRaw('c.kod, c.nama, c.jenis, ROUND(SUM(je.debit - je.kredit),2) as baki_dr')
            ->get()
            ->filter(fn ($r) => (float) $r->baki_dr != 0.0);

        $aset      = $rows->where('jenis', 'Aset')->map(fn ($r) => (object) ['kod' => $r->kod, 'nama' => $r->nama, 'amaun' => $r->baki_dr])->values();
        $liabiliti = $rows->where('jenis', 'Liabiliti')->map(fn ($r) => (object) ['kod' => $r->kod, 'nama' => $r->nama, 'amaun' => number_format(-(float) $r->baki_dr, 2, '.', '')])->values();
        $ekuiti    = $rows->where('jenis', 'Ekuiti')->map(fn ($r) => (object) ['kod' => $r->kod, 'nama' => $r->nama, 'amaun' => number_format(-(float) $r->baki_dr, 2, '.', '')])->values();

        $lebihanTerkumpul = round(
            -(float) $rows->whereIn('jenis', ['Hasil', 'Belanja'])->sum('baki_dr'), 2
        );

        $totalAset      = round((float) $aset->sum('amaun'), 2);
        $totalLiabiliti = round((float) $liabiliti->sum('amaun'), 2);
        $totalEkuiti    = round((float) $ekuiti->sum('amaun') + $lebihanTerkumpul, 2);

        return [
            'aset'              => $aset,
            'liabiliti'         => $liabiliti,
            'ekuiti'            => $ekuiti,
            'lebihan_terkumpul' => number_format($lebihanTerkumpul, 2, '.', ''),
            'total_aset'        => number_format($totalAset, 2, '.', ''),
            'total_liabiliti'   => number_format($totalLiabiliti, 2, '.', ''),
            'total_ekuiti'      => number_format($totalEkuiti, 2, '.', ''),
            'seimbang'          => abs($totalAset - ($totalLiabiliti + $totalEkuiti)) < 0.005,
        ];
    }

    /** Buku Jurnal — semua voucher POSTED dalam julat tarikh (+ tapis COA). */
    public function journalBook(string $dari, string $hingga, ?int $coaId = null, ?int $masjidId = null): Collection
    {
        $masjidId ??= app('current.masjid_id');

        $voucherIds = DB::table('journal_voucher as jv')
            ->where('jv.masjid_id', $masjidId)
            ->where('jv.status', 'POSTED')
            ->whereBetween('jv.tarikh', [$dari, $hingga])
            ->when($coaId, fn ($q) => $q->whereExists(fn ($s) => $s->selectRaw('1')
                ->from('journal_entry as je')->whereColumn('je.voucher_id', 'jv.id')->where('je.coa_id', $coaId)))
            ->orderBy('jv.tarikh')->orderBy('jv.id')
            ->pluck('jv.id');

        return DB::table('journal_voucher as jv')
            ->join('journal_entry as je', 'je.voucher_id', '=', 'jv.id')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->whereIn('jv.id', $voucherIds)
            ->orderBy('jv.tarikh')->orderBy('jv.id')->orderByDesc('je.debit')
            ->get(['jv.id as voucher_id', 'jv.voucher_ref', 'jv.tarikh', 'jv.deskripsi', 'jv.source_type',
                   'c.kod', 'c.nama', 'je.debit', 'je.kredit', 'je.memo'])
            ->groupBy('voucher_id')
            ->values();
    }

    /** Lejer akaun dengan baki berjalan (running balance). */
    public function ledgerActivity(int $coaId, string $dari, string $hingga, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $bakiAwal = (float) $this->base($masjidId)
            ->where('je.coa_id', $coaId)
            ->where('jv.tarikh', '<', $dari)
            ->selectRaw('COALESCE(SUM(je.debit - je.kredit),0) as b')
            ->value('b');

        $baris = $this->base($masjidId)
            ->where('je.coa_id', $coaId)
            ->whereBetween('jv.tarikh', [$dari, $hingga])
            ->orderBy('jv.tarikh')->orderBy('jv.id')
            ->get(['jv.tarikh', 'jv.voucher_ref', 'jv.deskripsi', 'je.memo', 'je.debit', 'je.kredit']);

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

    /** Laporan Mengikut Program — terima/belanja/net per tag program. $bankAccountId tapis ikut akaun bank (penyata ikut bank). */
    public function programReport(?string $dariYm = null, ?string $hinggaYm = null, ?int $masjidId = null, ?int $bankAccountId = null): Collection
    {
        $masjidId ??= app('current.masjid_id');

        $terima = DB::table('kutipan')
            ->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->whereNotNull('program')->where('program', '<>', '')
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($dariYm, fn ($q) => $q->where('period_ym', '>=', $dariYm))
            ->when($hinggaYm, fn ($q) => $q->where('period_ym', '<=', $hinggaYm))
            ->groupBy('program')->selectRaw('program, ROUND(SUM(jumlah),2) as jumlah')
            ->pluck('jumlah', 'program');

        $belanja = DB::table('pembayaran')
            ->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->whereNotNull('program')->where('program', '<>', '')
            ->whereIn('jenis', ['BAYARAN', 'ASET'])
            ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
            ->when($dariYm, fn ($q) => $q->where('period_ym', '>=', $dariYm))
            ->when($hinggaYm, fn ($q) => $q->where('period_ym', '<=', $hinggaYm))
            ->groupBy('program')->selectRaw('program, ROUND(SUM(jumlah),2) as jumlah')
            ->pluck('jumlah', 'program');

        return collect($terima->keys())->merge($belanja->keys())->unique()->sort()->values()
            ->map(fn ($p) => (object) [
                'program' => $p,
                'terima'  => number_format((float) ($terima[$p] ?? 0), 2, '.', ''),
                'belanja' => number_format((float) ($belanja[$p] ?? 0), 2, '.', ''),
                'net'     => number_format((float) ($terima[$p] ?? 0) - (float) ($belanja[$p] ?? 0), 2, '.', ''),
            ]);
    }

    /**
     * Rincian program SETIAP COA (untuk tanda nota kaki pada penyata).
     * Pulang ['terimaan' => Collection<kod => Collection<{program, jumlah}>>, 'belanja' => ...].
     */
    public function programByCoa(?string $dariYm = null, ?string $hinggaYm = null, ?int $masjidId = null, ?int $bankAccountId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        // Kumpul ikut kod COA → senarai {program, jumlah} (urutan jumlah desc)
        $kumpul = function (string $jadual, array $jenis = []) use ($masjidId, $dariYm, $hinggaYm, $bankAccountId) {
            $q = DB::table($jadual.' as x')
                ->join('coa as c', 'c.id', '=', 'x.coa_id')
                ->where('x.masjid_id', $masjidId)->where('x.status', 'ACTIVE')
                ->whereNotNull('x.program')->where('x.program', '<>', '')
                ->when($bankAccountId, fn ($q) => $q->where('x.bank_account_id', $bankAccountId))
                ->when($dariYm, fn ($q) => $q->where('x.period_ym', '>=', $dariYm))
                ->when($hinggaYm, fn ($q) => $q->where('x.period_ym', '<=', $hinggaYm));
            if ($jenis) {
                $q->whereIn('x.jenis', $jenis);
            }

            return $q->groupBy('c.kod', 'x.program')
                ->selectRaw('c.kod as kod, x.program as program, ROUND(SUM(x.jumlah),2) as jumlah')
                ->orderBy('c.kod')->orderByDesc('jumlah')
                ->get()
                ->groupBy('kod');
        };

        return [
            'terimaan' => $kumpul('kutipan'),
            'belanja'  => $kumpul('pembayaran', ['BAYARAN', 'ASET']),
        ];
    }

    /** Senarai unik nama program sedia ada (kutipan + pembayaran) — untuk cadangan borang. */
    public function programNames(?int $masjidId = null): Collection
    {
        $masjidId ??= app('current.masjid_id');

        $ambil = fn (string $jadual) => DB::table($jadual)
            ->where('masjid_id', $masjidId)->where('status', 'ACTIVE')
            ->whereNotNull('program')->where('program', '<>', '')
            ->distinct()->pluck('program');

        return $ambil('kutipan')->merge($ambil('pembayaran'))->unique()->sort()->values();
    }
}
