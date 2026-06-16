<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use App\Models\DepreciationSchedule;
use App\Models\FixedAsset;
use App\Services\Lanjutan\DepreciationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Fasa 9 — Susut Nilai: senarai aset aktif + jadual susut nilai bulanan +
 * jana & pos bulan semasa + pelupusan aset (modal sebab).
 */
class SusutNilaiController extends Controller
{
    public function __construct(private DepreciationService $servis)
    {
    }

    public function index(): View
    {
        $aset = FixedAsset::query()
            ->whereIn('status', ['AKTIF', 'DILUPUSKAN'])
            ->orderBy('status')->orderBy('kod_aset')
            ->get();

        $namaCoa = Coa::query()->whereIn('id', $aset->pluck('coa_id')->merge($aset->pluck('snt_coa_id'))->filter()->unique())
            ->get(['id', 'kod', 'nama'])->keyBy('id');

        $jadual = DepreciationSchedule::query()
            ->whereIn('fixed_asset_id', $aset->pluck('id'))
            ->orderByDesc('tahun')->orderByDesc('bulan')->orderByDesc('id')
            ->limit(60)
            ->get()
            ->groupBy('fixed_asset_id');

        return view('lanjutan.susut-nilai', [
            'aset'    => $aset,
            'namaCoa' => $namaCoa,
            'jadual'  => $jadual,
            'bulanan' => $aset->mapWithKeys(fn ($a) => [$a->id => $this->servis->susutBulanan($a)]),
        ]);
    }

    /** Jana & pos susut nilai bulan semasa untuk semua aset aktif. */
    public function jana(Request $request): RedirectResponse
    {
        $tahun = (int) $request->input('tahun', now()->year);
        $bulan = (int) $request->input('bulan', now()->month);
        abort_unless($bulan >= 1 && $bulan <= 12 && $tahun >= 2000 && $tahun <= 2100, 422);

        try {
            $r = $this->servis->janaBulan($tahun, $bulan);
        } catch (\Throwable $e) {
            return back()->withErrors(['jana' => 'Gagal menjana susut nilai: '.$e->getMessage()]);
        }

        return redirect()->route('susutnilai.index')->with('success',
            "Susut nilai {$r['period_ym']}: {$r['diposkan']} voucher diposkan (RM {$r['jumlah']}), {$r['dilangkau']} aset dilangkau (sudah diposkan / tiada kadar / susut penuh).");
    }

    /** Pelupusan aset — modal sebab → jurnal PELUPUSAN + status DILUPUSKAN. */
    public function lupus(Request $request, FixedAsset $aset): RedirectResponse
    {
        $data = $request->validate(
            ['sebab' => ['required', 'string', 'max:300']],
            [],
            ['sebab' => 'Sebab Pelupusan'],
        );

        try {
            $voucher = $this->servis->lupus($aset, $data['sebab']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['lupus' => $e->getMessage()]);
        }

        return redirect()->route('susutnilai.index')->with('success',
            "Aset {$aset->kod_aset} dilupuskan (voucher {$voucher->voucher_ref}).");
    }
}
