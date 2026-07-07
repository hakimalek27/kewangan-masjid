<?php

namespace App\Services\Lanjutan;

use App\Enums\SourceType;
use App\Models\JournalVoucher;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PeriodService;
use App\Services\Laporan\ReportService;
use App\Services\Security\AuditTrailService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Penutupan Tahun Kewangan (Fasa 9).
 *
 * KRITIKAL — voucher penutupan menggunakan period_ym 'YYYY-13' (tempoh maya
 * SELEPAS Disember; konvensyen sama seperti 'YYYY-00' untuk baki pembukaan).
 * Dengan itu:
 *   - P&L bulanan/tahunan SEJARAH TIDAK BERUBAH (P&L menapis YYYY-01..YYYY-12,
 *     jadi 'YYYY-13' terkecuali);
 *   - Kunci Kira-Kira (cutoff <=) tetap seimbang — baki Hasil/Belanja berpindah
 *     ke 100-10000 DANA TERKUMPUL (Ekuiti), jumlah ekuiti tidak berubah.
 *
 * Baris voucher: setiap akaun Hasil berbaki kredit → Dr; setiap akaun Belanja
 * berbaki debit → Cr; baki bersih → Cr (lebihan) / Dr (kurangan) 100-10000.
 * Ref 'YE-<tahun>'. Selepas pos: kunci tempoh sehingga '<tahun>-12' + audit log.
 */
class YearEndService
{
    public function __construct(
        private JournalService $journal,
        private PeriodService $period,
        private ReportService $report,
        private AuditTrailService $audit,
    ) {
    }

    /** Voucher penutupan tahun (jika sudah wujud) — kawalan tutup dua kali. */
    public function voucherPenutupan(int $tahun, ?int $masjidId = null): ?JournalVoucher
    {
        $masjidId ??= app('current.masjid_id');

        return JournalVoucher::withoutMasjidScope()
            ->where('masjid_id', $masjidId)
            ->where('voucher_ref', 'YE-'.$tahun)
            ->where('status', 'POSTED')
            ->first();
    }

    /** Pratonton sebelum tutup: P&L tahun + baki Akaun Sementara 300-99990. */
    public function pratonton(int $tahun, ?int $masjidId = null): array
    {
        $masjidId ??= app('current.masjid_id');

        $pl = $this->report->profitLoss("$tahun-01", "$tahun-12", $masjidId);

        $suspense = (float) DB::table('journal_entry as je')
            ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
            ->join('coa as c', 'c.id', '=', 'je.coa_id')
            ->where('jv.status', 'POSTED')
            ->where('jv.masjid_id', $masjidId)
            ->where('jv.period_ym', '<=', "$tahun-12")
            ->where('c.kod', config('spkm.coa.akaun_sementara'))
            ->selectRaw('COALESCE(SUM(je.kredit - je.debit),0) as b')
            ->value('b');

        return [
            'pl'            => $pl,
            'suspense'      => number_format($suspense, 2, '.', ''),
            'ada_suspense'  => abs($suspense) >= 0.005,
            'sudah_tutup'   => $this->voucherPenutupan($tahun, $masjidId) !== null,
            'locked_until'  => $this->period->lockedUntil($masjidId),
        ];
    }

    /** Tutup tahun — pos voucher penutupan 'YYYY-13' + kunci tempoh. */
    public function tutup(int $tahun, ?int $masjidId = null): JournalVoucher
    {
        $masjidId ??= app('current.masjid_id');

        if ($this->voucherPenutupan($tahun, $masjidId)) {
            throw new LogicException("Tahun $tahun telah pun ditutup (voucher YE-$tahun wujud).");
        }

        // C4 — WAJIB tutup tahun N-1 dahulu jika ia ADA aktiviti Hasil/Belanja belum
        // ditutup. Jika tidak, jumlah kumulatif (period_ym <= 'YYYY-12') akan melipat
        // baki tahun terdahulu ke dalam voucher penutupan tahun ini, dan kunci tempoh
        // menjadikannya TAK BOLEH dibetulkan. (Tahun pertama berdata dibenarkan kerana
        // tahun sebelumnya tiada aktiviti.)
        $tahunSebelum = $tahun - 1;
        $plSebelum = $this->report->profitLoss("$tahunSebelum-01", "$tahunSebelum-12", $masjidId);
        $adaAktivitiSebelum = abs((float) $plSebelum['jumlah_hasil']) >= 0.005
            || abs((float) $plSebelum['jumlah_belanja']) >= 0.005;
        if ($adaAktivitiSebelum && ! $this->voucherPenutupan($tahunSebelum, $masjidId)) {
            throw new LogicException("Tutup tahun $tahunSebelum dahulu sebelum menutup tahun $tahun.");
        }

        $dana = $this->journal->coaByKod(config('spkm.coa.dana_terkumpul'), $masjidId);
        $bersih = '0.00';

        $voucher = DB::transaction(function () use ($tahun, $masjidId, $dana, &$bersih) {
            // Semak semula dalam transaksi (cegah tutup-dua-kali berlumba).
            if ($this->voucherPenutupan($tahun, $masjidId)) {
                throw new LogicException("Tahun $tahun telah pun ditutup (voucher YE-$tahun wujud).");
            }

            // C4 — KUNCI tempoh DAHULU supaya sebarang pos baharu ke tahun ini ditolak
            // (assertOpen), kemudian kira baki DALAM transaksi (kurangkan tetingkap TOCTOU).
            $this->period->lockUntil("$tahun-12", $masjidId);

            // Baki kumulatif setiap akaun Hasil/Belanja sehingga hujung tahun
            // (termasuk 'YYYY-00' pembukaan & penutupan terdahulu 'YYYY-13').
            // Hanya akaun boleh-pos (bukan kepala, aktif).
            $rows = DB::table('journal_entry as je')
                ->join('journal_voucher as jv', 'jv.id', '=', 'je.voucher_id')
                ->join('coa as c', 'c.id', '=', 'je.coa_id')
                ->where('jv.status', 'POSTED')
                ->where('jv.masjid_id', $masjidId)
                ->where('jv.period_ym', '<=', "$tahun-12")
                ->whereIn('c.jenis', ['Hasil', 'Belanja'])
                ->where('c.is_header', 0)
                ->where('c.is_active', 1)
                ->groupBy('je.coa_id', 'c.kod')
                ->orderBy('c.kod')
                ->selectRaw('je.coa_id, c.kod, ROUND(SUM(je.debit - je.kredit),2) as baki_dr')
                ->get()
                ->filter(fn ($r) => abs((float) $r->baki_dr) >= 0.005);

            if ($rows->isEmpty()) {
                throw new LogicException("Tiada baki Hasil/Belanja untuk ditutup bagi tahun $tahun.");
            }

            $lines = [];
            $bersih = '0.00'; // (+) lebihan, (−) kurangan (dikongsi keluar untuk webhook)
            foreach ($rows as $r) {
                $baki = (float) $r->baki_dr;
                if ($baki < 0) {
                    $lines[] = ['coa_id' => (int) $r->coa_id, 'debit' => -$baki, 'kredit' => 0, 'memo' => 'Penutupan '.$tahun];
                    $bersih = bcadd($bersih, number_format(-$baki, 2, '.', ''), 2);
                } else {
                    $lines[] = ['coa_id' => (int) $r->coa_id, 'debit' => 0, 'kredit' => $baki, 'memo' => 'Penutupan '.$tahun];
                    $bersih = bcsub($bersih, number_format($baki, 2, '.', ''), 2);
                }
            }

            // Baki bersih → DANA TERKUMPUL 100-10000
            if (bccomp($bersih, '0.00', 2) > 0) {
                $lines[] = ['coa_id' => $dana->id, 'debit' => 0, 'kredit' => $bersih, 'memo' => "Lebihan tahun $tahun"];
            } elseif (bccomp($bersih, '0.00', 2) < 0) {
                $lines[] = ['coa_id' => $dana->id, 'debit' => bcmul($bersih, '-1', 2), 'kredit' => 0, 'memo' => "Kurangan tahun $tahun"];
            }

            $voucher = $this->journal->post(
                tarikh: "$tahun-12-31",
                sourceType: SourceType::JURNAL,
                sourceId: null,
                deskripsi: "PENUTUPAN TAHUN $tahun — pindah lebihan/(kurangan) Hasil & Belanja ke DANA TERKUMPUL",
                lines: $lines,
                voucherRef: 'YE-'.$tahun,
                periodYm: "$tahun-13", // KRITIKAL: di luar julat P&L YYYY-01..YYYY-12
                masjidId: $masjidId,
            );

            // (Tempoh sudah dikunci di awal transaksi — tidak perlu ulang.)

            $this->audit->log('APPROVE', 'journal_voucher', null, [
                'tindakan' => 'TUTUP_TAHUN',
                'tahun'    => $tahun,
                'bersih'   => $bersih,
                'voucher'  => 'YE-'.$tahun,
                'kunci'    => "$tahun-12",
            ], $voucher->id, masjidId: $masjidId);

            return $voucher;
        });

        // Webhook report.closed (best-effort) — selepas penutupan berjaya
        try {
            app(\App\Services\Api\WebhookDispatcher::class)->dispatch('report.closed', [
                'tahun' => $tahun, 'bersih' => $bersih, 'voucher_ref' => $voucher->voucher_ref,
            ], (int) $masjidId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $voucher;
    }
}
