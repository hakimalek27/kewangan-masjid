<?php

namespace App\Http\Controllers\Web\Statistik;

use App\Http\Controllers\Controller;
use App\Services\Laporan\StatistikService;
use Illuminate\Http\Request;

/**
 * Statistik kewangan — agregat asas tunai (kutipan/pembayaran ACTIVE),
 * selari listing SPPKMS. Carta Chart.js (window.Chart, global).
 */
class StatistikController extends Controller
{
    public function __construct(private StatistikService $statistik)
    {
    }

    /** Statistik Kutipan Tahunan — kategori × 12 bulan + carta. */
    public function kutipan(Request $request)
    {
        return $this->paparTahunan($request, 'kutipan', 'Statistik Kutipan Tahunan');
    }

    /** Statistik Perbelanjaan Tahunan. */
    public function belanja(Request $request)
    {
        return $this->paparTahunan($request, 'pembayaran', 'Statistik Perbelanjaan Tahunan');
    }

    private function paparTahunan(Request $request, string $jenis, string $tajuk)
    {
        $tahun = (int) $request->input('year', now()->year);
        $coaId = (int) $request->input('coa_id') ?: null;
        $data = $this->statistik->tahunan($jenis, $tahun, $coaId);

        // Jumlah bulanan keseluruhan (semua kategori) untuk carta bar
        $bulanan = [];
        for ($b = 1; $b <= 12; $b++) {
            $bulanan[$b] = round($data['kategori']->sum(fn ($k) => (float) $k->bulanan[$b]), 2);
        }

        return view('statistik.tahunan', [
            'tajuk'   => $tajuk,
            'jenis'   => $jenis,
            'tahun'   => $tahun,
            'data'    => $data,
            'bulanan' => $bulanan,
        ]);
    }

    /** Statistik Kutipan Bulanan ikut COA. */
    public function kutipanCoa(Request $request)
    {
        return $this->paparBulanan($request, 'kutipan', 'Statistik Kutipan Bulanan');
    }

    /** Statistik Perbelanjaan Bulanan ikut COA. */
    public function belanjaCoa(Request $request)
    {
        return $this->paparBulanan($request, 'pembayaran', 'Statistik Perbelanjaan Bulanan');
    }

    private function paparBulanan(Request $request, string $jenis, string $tajuk)
    {
        $ym = $this->periodYm($request);

        return view('statistik.bulanan', [
            'tajuk' => $tajuk,
            'jenis' => $jenis,
            'ym'    => $ym,
            'data'  => $this->statistik->bulanan($jenis, $ym),
        ]);
    }

    /** Statistik Kutipan Jumaat — pecahan mingguan + carta. */
    public function jumaat(Request $request)
    {
        $ym = $this->periodYm($request);
        $senarai = $this->statistik->jumaatMingguan($ym);

        return view('statistik.jumaat', [
            'ym'      => $ym,
            'senarai' => $senarai,
            'jumlah'  => number_format($senarai->sum(fn ($r) => (float) $r->jumlah), 2, '.', ''),
        ]);
    }

    private function periodYm(Request $request): string
    {
        return sprintf('%04d-%02d', (int) $request->input('year', now()->year), (int) $request->input('bln', now()->month));
    }
}
