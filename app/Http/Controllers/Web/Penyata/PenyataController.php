<?php

namespace App\Http\Controllers\Web\Penyata;

use App\Exports\JadualExport;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Coa;
use App\Models\Masjid;
use App\Services\Laporan\ReportService;
use App\Services\Laporan\StatementService;
use App\Support\UserSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Penyata (buku tunai) — format 2 lajur replika penyataTP SPPKMS:
 * KIRI (Baki Awal → Terimaan → Pindahan PWR) = KANAN (Belanja → Baki Akhir + Pindahan).
 */
class PenyataController extends Controller
{
    /** Saiz font paparan penyata (replika pilihan fontSize SPPKMS). */
    private const SAIZ_FONT = ['kecil' => '10px', 'sederhana' => '12px', 'besar' => '14px', 'xbesar' => '18px'];

    public function __construct(private StatementService $statement, private ReportService $report)
    {
    }

    /** Penyata Bulanan — 2 lajur seimbang. */
    public function bulanan(Request $request)
    {
        return $this->paparBulanan($request, null);
    }

    /** Penyata Bulanan Ikut Bank — sama, tajuk tambah nama bank dipilih. */
    public function bank(Request $request)
    {
        $bank = $request->filled('bank_account_id')
            ? BankAccount::withoutMasjidScope()
                ->where('masjid_id', app('current.masjid_id'))
                ->find((int) $request->input('bank_account_id'))
            : null;

        return $this->paparBulanan($request, $bank);
    }

    private function paparBulanan(Request $request, ?BankAccount $bank)
    {
        $ym = $this->periodYm($request);
        // Penyata Ikut Bank → tapis kepada akaun bank dipilih; penyata biasa → gabungan
        $p = $this->statement->monthly($ym, null, $bank?->id);

        $pindahan = number_format((float) collect($p['pindahan_pwr'])->sum('jumlah'), 2, '.', '');
        $jumlahKiri = number_format((float) $p['jumlah_baki_awal'] + (float) $p['jumlah_terimaan'] + (float) $pindahan, 2, '.', '');
        $jumlahKanan = number_format((float) $p['jumlah_belanja'] + (float) $p['jumlah_baki_akhir'] + (float) $pindahan, 2, '.', '');

        $font = self::SAIZ_FONT[$request->input('font', 'sederhana')] ?? '12px';
        $nota = $request->boolean('nota');
        $nf = $nota ? $this->binaNota($p['terimaan'], $p['belanja'], $this->report->programByCoa($ym, $ym, null, $bank?->id)) : ['map' => [], 'senarai' => []];
        $data = [
            'p'            => $p,
            'ym'           => $ym,
            'pindahan'     => $pindahan,
            'jumlah_kiri'  => $jumlahKiri,
            'jumlah_kanan' => $jumlahKanan,
            'font'         => $font,
            'bank'         => $bank,
            'namaMasjid'   => $this->namaMasjid(),
            'gaya'         => $this->gaya($request),
            'nota'         => $nota,
            'notaData'     => $nota ? $this->report->programReport($ym, $ym, null, $bank?->id) : collect(),
            'noteMap'      => $nf['map'],
            'notaList'     => $nf['senarai'],
        ];

        if ($format = $this->format($request)) {
            if ($format === 'pdf') {
                ini_set('memory_limit', '512M'); // dompdf + A3 perlu ruang lebih
                return Pdf::loadView('pdf.penyata-bulanan', $data)
                    ->setPaper('a3', 'portrait')
                    ->download('penyata-bulanan-'.$ym.'.pdf');
            }
            $rows = [];
            foreach ($p['baki_awal'] as $r) {
                $rows[] = ['BAKI AWAL (B/B)', $r->kod, $r->nama, $r->baki];
            }
            foreach ($p['terimaan'] as $r) {
                $rows[] = ['TERIMAAN', $r->kod, $r->nama, $r->jumlah];
            }
            foreach ($p['pindahan_pwr'] as $r) {
                $rows[] = ['PELARASAN PINDAHAN PWR', $r->kod, $r->nama, $r->jumlah];
            }
            foreach ($p['belanja'] as $r) {
                $rows[] = ['PERBELANJAAN', $r->kod, $r->nama, $r->jumlah];
            }
            foreach ($p['baki_akhir'] as $r) {
                $rows[] = ['BAKI AKHIR (B/H)', $r->kod, $r->nama, $r->baki];
            }
            $rows[] = ['JUMLAH (KIRI)', '', '', $jumlahKiri];
            $rows[] = ['JUMLAH (KANAN)', '', '', $jumlahKanan];

            return Excel::download(
                new JadualExport(['Seksyen', 'Kod', 'Akaun', 'Amaun (RM)'], $rows),
                'penyata-bulanan-'.$ym.'.xlsx'
            );
        }

        return view('penyata.bulanan', $data);
    }

    /** Penyata Tahunan — 12 bulan: Terima | Bayar Bank | Bayar PWR | Bayar Tunai. */
    public function tahunan(Request $request)
    {
        $tahun = (int) $request->input('year', now()->year);
        $t = $this->statement->yearly($tahun);                 // grid 12-bulan (sokongan)
        $ps = $this->statement->yearlyStatement($tahun);       // penyata 2-lajur (format V2)

        $grid = $request->boolean('grid');
        $nota = $request->boolean('nota');
        $dari = sprintf('%04d-01', $tahun);
        $hingga = sprintf('%04d-12', $tahun);
        $notaData = $nota ? $this->report->programReport($dari, $hingga) : collect();
        $nf = $nota ? $this->binaNota($ps['terimaan'], $ps['belanja'], $this->report->programByCoa($dari, $hingga)) : ['map' => [], 'senarai' => []];
        $data = [
            't' => $t, 'ps' => $ps, 'tahun' => $tahun, 'namaMasjid' => $this->namaMasjid(),
            'gaya' => $this->gaya($request), 'grid' => $grid, 'nota' => $nota, 'notaData' => $notaData,
            'noteMap' => $nf['map'], 'notaList' => $nf['senarai'],
        ];

        if ($format = $this->format($request)) {
            if ($format === 'pdf') {
                ini_set('memory_limit', '512M'); // dompdf + A3 perlu ruang lebih
                return Pdf::loadView('pdf.penyata-tahunan', $data)
                    ->setPaper('a3', 'portrait')
                    ->download('penyata-tahunan-'.$tahun.'.pdf');
            }
            $rows = collect($t['bulanan'])
                ->map(fn ($r, $ym) => [$ym, $r['terima'], $r['bayar_bank'], $r['bayar_pwr'], $r['bayar_tunai']])
                ->values()
                ->push(['JUMLAH', $t['jumlah']['terima'], $t['jumlah']['bayar_bank'], $t['jumlah']['bayar_pwr'], $t['jumlah']['bayar_tunai']])
                ->all();

            return Excel::download(new JadualExport(
                ['Bulan', 'Terima (RM)', 'Bayar Bank (RM)', 'Bayar PWR (RM)', 'Bayar Tunai (RM)'], $rows
            ), 'penyata-tahunan-'.$tahun.'.xlsx');
        }

        return view('penyata.tahunan', $data);
    }

    /** Baki Di Tangan PWR — baki akhir setiap bulan untuk satu akaun PWR. */
    public function pwrBaki(Request $request)
    {
        $tahun = (int) $request->input('year', now()->year);
        $coa = $this->coaPwr($request);

        $baki = [];
        if ($coa) {
            for ($b = 1; $b <= 12; $b++) {
                $ym = sprintf('%04d-%02d', $tahun, $b);
                $baki[$ym] = $this->statement->pwrStatement($coa->id, $ym)['baki_akhir'];
            }
        }

        return view('penyata.pwr-baki', ['coa' => $coa, 'tahun' => $tahun, 'baki' => $baki]);
    }

    /** Penyata PWR — buku tunai satu akaun PWR untuk bulan dipilih. */
    public function pwrPenyata(Request $request)
    {
        $ym = $this->periodYm($request);
        $coa = $this->coaPwr($request);

        return view('penyata.pwr-penyata', [
            'coa'     => $coa,
            'ym'      => $ym,
            'penyata' => $coa ? $this->statement->pwrStatement($coa->id, $ym) : null,
        ]);
    }

    // ---------- helper ----------

    /** Akaun PWR dipilih (julat 250-06); lalai akaun PWR pertama. */
    private function coaPwr(Request $request): ?Coa
    {
        $q = Coa::withoutMasjidScope()
            ->where('masjid_id', app('current.masjid_id'))
            ->where('kod', 'like', '250-06%')
            ->where('is_header', 0);

        if ($request->filled('pwr_coa_id')) {
            return (clone $q)->find((int) $request->input('pwr_coa_id')) ?? $q->orderBy('kod')->first();
        }

        return $q->orderBy('kod')->first();
    }

    /**
     * Bina peta nota kaki: setiap COA terimaan/perbelanjaan yang ada program bertag diberi
     * nombor nota (terimaan dahulu, kemudian perbelanjaan — ikut urutan paparan).
     * Pulang ['map' => [kod => no], 'senarai' => [{no, kod, nama, progs}]].
     */
    private function binaNota(iterable $terimaan, iterable $belanja, array $programByCoa): array
    {
        $map = [];
        $senarai = [];
        $no = 0;
        // Kunci ikut SISI+kod kerana satu COA boleh wujud di kedua-dua terimaan & perbelanjaan
        // (cth 300-04050 KUMPULAN TABUNG RAHMAH MADANI) — elak nombor bertindih.
        foreach ([['T', $terimaan], ['B', $belanja]] as [$pre, $baris]) {
            $sisi = $pre === 'T' ? 'terimaan' : 'belanja';
            foreach ($baris as $r) {
                $progs = $programByCoa[$sisi]->get($r->kod ?? null);
                if ($progs && $progs->isNotEmpty()) {
                    $no++;
                    $map[$pre.':'.$r->kod] = $no;
                    // Rekonsiliasi: program bertag selalunya hanya SEBAHAGIAN jumlah baris COA.
                    // Tambah baki "(Tiada tag program)" supaya nota kaki = jumlah baris yang ditanda.
                    $progsFinal = $progs->values();
                    $baki = round((float) ($r->jumlah ?? 0) - $progsFinal->sum(fn ($x) => (float) $x->jumlah), 2);
                    if ($baki >= 0.01) {
                        $progsFinal = $progsFinal->push((object) ['program' => '(Tiada tag program)', 'jumlah' => $baki]);
                    }
                    $senarai[] = (object) ['no' => $no, 'kod' => $r->kod, 'nama' => $r->nama, 'progs' => $progsFinal];
                }
            }
        }

        return ['map' => $map, 'senarai' => $senarai];
    }

    /**
     * Gaya paparan penyata: 'v1' (bersih, replika mesrasuci) atau 'semasa' (Bootstrap berbingkai).
     * PER-PENGGUNA (jadual user_setting): jika ?gaya= dihantar → simpan pilihan pengguna; jika tidak → baca (lalai 'v1').
     */
    private function gaya(Request $request): string
    {
        $semasa = UserSetting::get('gaya_penyata', 'v1');
        $g = $request->input('gaya');
        if (! in_array($g, ['v1', 'semasa'], true)) {
            return $semasa;
        }
        // Pilihan PER-PENGGUNA: simpan HANYA pada paparan skrin (bukan eksport PDF/XLS) dan HANYA jika
        // berubah — elak tulis DB berulang pada GET. Tidak menjejaskan pengguna lain.
        if ($g !== $semasa && ! $this->format($request)) {
            UserSetting::set('gaya_penyata', $g);
        }

        return $g;
    }

    private function periodYm(Request $request): string
    {
        return sprintf('%04d-%02d', (int) $request->input('year', now()->year), (int) $request->input('bln', now()->month));
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
}
