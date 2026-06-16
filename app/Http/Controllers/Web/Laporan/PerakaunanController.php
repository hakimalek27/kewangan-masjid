<?php

namespace App\Http\Controllers\Web\Laporan;

use App\Exports\JadualExport;
use App\Http\Controllers\Controller;
use App\Models\Coa;
use App\Models\JournalVoucher;
use App\Models\Masjid;
use App\Services\Accounting\VoidService;
use App\Services\Laporan\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Penyata Perakaunan (8 halaman) — semua angka datang TERUS daripada
 * ReportService (jurnal POSTED). Controller hanya susun input & paparan.
 */
class PerakaunanController extends Controller
{
    public function __construct(private ReportService $report)
    {
    }

    /** Carta Akaun penuh (header dipapar tebal) + eksport PDF/Excel. */
    public function coa(Request $request)
    {
        $senarai = Coa::withoutMasjidScope()
            ->where('masjid_id', app('current.masjid_id'))
            ->orderBy('kod')
            ->get(['kod', 'nama', 'jenis', 'normal_balance', 'is_header', 'is_active']);

        if ($format = $this->format($request)) {
            $rows = $senarai->map(fn ($c) => [
                $c->kod,
                $c->nama,
                $c->jenis,
                $c->is_header ? '—' : $c->normal_balance,
            ])->all();

            return $this->eksport($format, 'carta-akaun', 'pdf.coa',
                ['senarai' => $senarai, 'namaMasjid' => $this->namaMasjid()],
                ['Kod', 'Nama Akaun', 'Jenis', 'Baki Normal'], $rows);
        }

        return view('akaun.coa', ['senarai' => $senarai]);
    }

    /** Buku Jurnal — semua voucher POSTED dalam julat tarikh + eksport PDF/Excel. */
    public function jurnal(Request $request)
    {
        [$dari, $hingga] = $this->julatTarikh($request);
        $coaId = (int) $request->input('coa_id') ?: null;
        $vouchers = $this->report->journalBook($dari, $hingga, $coaId);

        if ($format = $this->format($request)) {
            return $this->eksportJurnal($format, 'Buku Jurnal', $vouchers, $dari, $hingga);
        }

        return view('akaun.jurnal', [
            'tajuk'    => 'Buku Jurnal',
            'vouchers' => $vouchers,
            'dari'     => $dari,
            'hingga'   => $hingga,
            'coaWajib' => false,
        ]);
    }

    /** Laporan Jurnal ikut akaun — sama seperti Buku Jurnal tetapi COA wajib dipilih. */
    public function laporanJurnal(Request $request)
    {
        [$dari, $hingga] = $this->julatTarikh($request);
        $coaId = (int) $request->input('coa_id') ?: null;
        $vouchers = $coaId ? $this->report->journalBook($dari, $hingga, $coaId) : collect();

        if (($format = $this->format($request)) && $coaId) {
            return $this->eksportJurnal($format, 'Laporan Jurnal Mengikut Akaun', $vouchers, $dari, $hingga);
        }

        return view('akaun.jurnal', [
            'tajuk'    => 'Laporan Jurnal Mengikut Akaun',
            'vouchers' => $vouchers,
            'dari'     => $dari,
            'hingga'   => $hingga,
            'coaWajib' => true,
        ]);
    }

    /** Eksport Buku/Laporan Jurnal — satu baris Excel per catatan jurnal. */
    private function eksportJurnal(string $format, string $tajuk, $vouchers, string $dari, string $hingga)
    {
        $rows = [];
        foreach ($vouchers as $entries) {
            foreach ($entries as $e) {
                $rows[] = [
                    \Illuminate\Support\Carbon::parse($e->tarikh)->format('d/m/Y'),
                    $e->voucher_ref,
                    trim($e->kod.' '.$e->nama),
                    $e->memo,
                    (float) $e->debit > 0 ? number_format((float) $e->debit, 2) : '',
                    (float) $e->kredit > 0 ? number_format((float) $e->kredit, 2) : '',
                ];
            }
        }

        return $this->eksport($format, 'buku-jurnal-'.$dari.'-hingga-'.$hingga, 'pdf.jurnal',
            ['vouchers' => $vouchers, 'tajuk' => $tajuk, 'dari' => $dari, 'hingga' => $hingga, 'namaMasjid' => $this->namaMasjid()],
            ['Tarikh', 'Ref', 'Akaun', 'Memo', 'Debit (RM)', 'Kredit (RM)'], $rows);
    }

    /** Batal jurnal = VOID + voucher pembalik (jejak audit kekal). */
    public function jurnalBatal(int $voucher): RedirectResponse
    {
        $v = JournalVoucher::withoutMasjidScope()
            ->where('masjid_id', app('current.masjid_id'))
            ->findOrFail($voucher);

        app(VoidService::class)->voidVoucher($v, 'Dibatalkan melalui Buku Jurnal');

        return back()->with('success', 'Jurnal dibatalkan');
    }

    /** Lejer Am. */
    public function lejer(Request $request)
    {
        return $this->paparLejer($request, 'Lejer Am');
    }

    /** Lejer Mengikut Akaun (running balance). */
    public function lejerAkaun(Request $request)
    {
        return $this->paparLejer($request, 'Lejer Mengikut Akaun');
    }

    private function paparLejer(Request $request, string $tajuk)
    {
        [$dari, $hingga] = $this->julatTarikh($request);
        $coaId = (int) $request->input('coa_id') ?: null;
        $coa = $coaId ? Coa::withoutMasjidScope()->where('masjid_id', app('current.masjid_id'))->find($coaId) : null;
        $lejer = $coaId ? $this->report->ledgerActivity($coaId, $dari, $hingga) : null;

        if (($format = $this->format($request)) && $lejer) {
            $rows = [['', '', 'BAKI AWAL (B/B)', '', '', $lejer['baki_awal']]];
            foreach ($lejer['baris'] as $r) {
                $rows[] = [
                    \Illuminate\Support\Carbon::parse($r->tarikh)->format('d/m/Y'),
                    $r->voucher_ref,
                    $r->deskripsi ?: $r->memo,
                    (float) $r->debit > 0 ? number_format((float) $r->debit, 2) : '',
                    (float) $r->kredit > 0 ? number_format((float) $r->kredit, 2) : '',
                    number_format((float) $r->baki, 2),
                ];
            }
            $rows[] = ['', '', 'BAKI AKHIR (B/H)', '', '', $lejer['baki_akhir']];

            return $this->eksport($format, 'lejer-'.($coa?->kod ?? 'akaun').'-'.$dari.'-hingga-'.$hingga, 'pdf.lejer',
                ['lejer' => $lejer, 'coa' => $coa, 'tajuk' => $tajuk, 'dari' => $dari, 'hingga' => $hingga, 'namaMasjid' => $this->namaMasjid()],
                ['Tarikh', 'Ref', 'Keterangan', 'Debit (RM)', 'Kredit (RM)', 'Baki (RM)'], $rows);
        }

        return view('akaun.lejer', [
            'tajuk'  => $tajuk,
            'coa'    => $coa,
            'lejer'  => $lejer,
            'dari'   => $dari,
            'hingga' => $hingga,
        ]);
    }

    /** Imbangan Duga pada cutoff bulan/tahun. */
    public function imbangan(Request $request)
    {
        $ym = $this->periodYm($request);
        $tb = $this->report->trialBalance($ym);

        if ($format = $this->format($request)) {
            $rows = collect($tb['baris'])
                ->map(fn ($r) => [$r->kod, $r->nama, $r->debit, $r->kredit])
                ->push(['', 'JUMLAH', $tb['jumlah_debit'], $tb['jumlah_kredit']])
                ->all();

            return $this->eksport($format, 'imbangan-duga-'.$ym, 'pdf.imbangan',
                ['tb' => $tb, 'ym' => $ym, 'namaMasjid' => $this->namaMasjid()],
                ['Kod', 'Nama Akaun', 'Debit (RM)', 'Kredit (RM)'], $rows);
        }

        return view('akaun.imbangan', ['tb' => $tb, 'ym' => $ym]);
    }

    /** Untung Rugi — mod ringkas/terperinci, julat bulan dari–hingga. */
    public function untungRugi(Request $request)
    {
        $tahun = (int) $request->input('year', now()->year);
        $dari = sprintf('%04d-%02d', (int) $request->input('dari_year', $tahun), (int) $request->input('dari_bln', 1));
        $hingga = sprintf('%04d-%02d', $tahun, (int) $request->input('bln', now()->month));
        if ($dari > $hingga) {
            [$dari, $hingga] = [$hingga, $dari];
        }
        $mode = $request->input('mode', 'terperinci') === 'ringkas' ? 'ringkas' : 'terperinci';
        $pl = $this->report->profitLoss($dari, $hingga);

        if ($format = $this->format($request)) {
            $rows = [];
            if ($mode === 'terperinci') {
                foreach ($pl['hasil'] as $r) {
                    $rows[] = [$r->kod, $r->nama, 'Pendapatan', $r->amaun];
                }
                foreach ($pl['belanja'] as $r) {
                    $rows[] = [$r->kod, $r->nama, 'Perbelanjaan', $r->amaun];
                }
            }
            $rows[] = ['', 'JUMLAH PENDAPATAN', '', $pl['jumlah_hasil']];
            $rows[] = ['', 'JUMLAH PERBELANJAAN', '', $pl['jumlah_belanja']];
            $rows[] = ['', 'LEBIHAN/(KURANGAN)', '', $pl['lebihan']];

            return $this->eksport($format, 'untung-rugi-'.$dari.'-hingga-'.$hingga, 'pdf.untungrugi',
                ['pl' => $pl, 'dari' => $dari, 'hingga' => $hingga, 'mode' => $mode, 'namaMasjid' => $this->namaMasjid()],
                ['Kod', 'Nama Akaun', 'Seksyen', 'Amaun (RM)'], $rows);
        }

        return view('akaun.untungrugi', ['pl' => $pl, 'dari' => $dari, 'hingga' => $hingga, 'mode' => $mode]);
    }

    /** Kunci Kira-Kira pada cutoff bulan/tahun. */
    public function kunci(Request $request)
    {
        $ym = $this->periodYm($request);
        $bs = $this->report->balanceSheet($ym);

        if ($format = $this->format($request)) {
            $rows = [];
            foreach (['aset' => 'ASET', 'liabiliti' => 'LIABILITI', 'ekuiti' => 'EKUITI'] as $kunci => $label) {
                foreach ($bs[$kunci] as $r) {
                    $rows[] = [$r->kod, $r->nama, $label, $r->amaun];
                }
            }
            $rows[] = ['', 'Lebihan/(Kurangan) Terkumpul', 'EKUITI', $bs['lebihan_terkumpul']];
            $rows[] = ['', 'JUMLAH ASET', '', $bs['total_aset']];
            $rows[] = ['', 'JUMLAH LIABILITI', '', $bs['total_liabiliti']];
            $rows[] = ['', 'JUMLAH EKUITI', '', $bs['total_ekuiti']];

            return $this->eksport($format, 'kunci-kira-kira-'.$ym, 'pdf.kunci',
                ['bs' => $bs, 'ym' => $ym, 'namaMasjid' => $this->namaMasjid()],
                ['Kod', 'Nama Akaun', 'Seksyen', 'Amaun (RM)'], $rows);
        }

        return view('akaun.kunci', ['bs' => $bs, 'ym' => $ym]);
    }

    /** Laporan Mengikut Program. */
    public function program(Request $request)
    {
        $dari = $request->filled('dari_year')
            ? sprintf('%04d-%02d', (int) $request->input('dari_year'), (int) $request->input('dari_bln', 1)) : null;
        $hingga = $request->filled('year')
            ? sprintf('%04d-%02d', (int) $request->input('year'), (int) $request->input('bln', 12)) : null;

        $program = $this->report->programReport($dari, $hingga);
        $jumlah = [
            'terima'  => number_format($program->sum(fn ($p) => (float) $p->terima), 2, '.', ''),
            'belanja' => number_format($program->sum(fn ($p) => (float) $p->belanja), 2, '.', ''),
            'net'     => number_format($program->sum(fn ($p) => (float) $p->net), 2, '.', ''),
        ];

        if ($format = $this->format($request)) {
            $rows = $program->map(fn ($p) => [$p->program, $p->terima, $p->belanja, $p->net])
                ->push(['JUMLAH', $jumlah['terima'], $jumlah['belanja'], $jumlah['net']])
                ->all();

            return $this->eksport($format, 'laporan-program', 'pdf.program',
                ['program' => $program, 'jumlah' => $jumlah, 'dari' => $dari, 'hingga' => $hingga, 'namaMasjid' => $this->namaMasjid()],
                ['Program', 'Terima (RM)', 'Belanja (RM)', 'Net (RM)'], $rows);
        }

        return view('akaun.program', ['program' => $program, 'jumlah' => $jumlah, 'dari' => $dari, 'hingga' => $hingga]);
    }

    // ---------- helper kongsi ----------

    private function periodYm(Request $request): string
    {
        return sprintf('%04d-%02d', (int) $request->input('year', now()->year), (int) $request->input('bln', now()->month));
    }

    private function julatTarikh(Request $request): array
    {
        $dari = $request->input('date_from') ?: now()->startOfMonth()->format('Y-m-d');
        $hingga = $request->input('date_to') ?: now()->endOfMonth()->format('Y-m-d');

        return $dari <= $hingga ? [$dari, $hingga] : [$hingga, $dari];
    }

    private function format(Request $request): ?string
    {
        $f = $request->input('format');

        return in_array($f, ['pdf', 'xls'], true) ? $f : null;
    }

    private function namaMasjid(): string
    {
        // Masjid AKTIF (ikut pilihan switcher), bukan masjid asal pengguna.
        return Masjid::semasa()?->nama ?? config('app.name');
    }

    private function eksport(string $format, string $namaFail, string $pdfView, array $data, array $headings, array $rows)
    {
        if ($format === 'pdf') {
            return Pdf::loadView($pdfView, $data)->download($namaFail.'.pdf');
        }

        return Excel::download(new JadualExport($headings, $rows), $namaFail.'.xlsx');
    }
}
