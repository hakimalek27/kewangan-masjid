<?php

namespace App\Http\Controllers\Web\Aset;

use App\Http\Controllers\Controller;
use App\Http\Requests\Aset\AsetOpeningRequest;
use App\Models\Coa;
use App\Models\DepreciationSchedule;
use App\Models\FixedAsset;
use App\Services\Transaksi\AsetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AsetController extends Controller
{
    public function __construct(private AsetService $aset)
    {
    }

    /** Borang Daftar Aset Lama — tiada jurnal, nilai melalui Baki Awal (replika fixed_asset_opening_balance_add.php). */
    public function daftarLama(): View
    {
        // Akaun aset 200-01 (bukan SNT kontra '...5') + maklumat SNT padanan untuk paparan auto
        $coaAset = Coa::postable()
            ->where('kod', 'like', '200-01%')
            ->where('kod', 'not like', '%5')
            ->orderBy('kod')
            ->get(['id', 'kod', 'nama'])
            ->map(function ($coa) {
                $kodSnt = substr($coa->kod, 0, -1).'5';
                $snt = Coa::postable()->where('kod', $kodSnt)->first(['kod', 'nama']);
                $coa->snt_info = $snt ? $snt->kod.' '.$snt->nama : '— tiada akaun SNT padanan —';

                return $coa;
            });

        return view('aset.daftar-lama', compact('coaAset'));
    }

    public function simpan(AsetOpeningRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['kod_aset'])) {
            unset($data['kod_aset']); // biar service jana kod auto
        }

        $this->aset->registerOpening($data);

        return redirect()
            ->route('aset.senarai')
            ->with('success', 'Aset lama berjaya didaftarkan (tiada jurnal — nilai melalui Baki Awal)');
    }

    /** Senarai Aset dengan penapis Semua/Alih/Tidak Alih (replika fixed_assets_listing_v1.php). */
    public function senarai(Request $request): View
    {
        $jenis = $request->input('jenis'); // null=Semua | ALIH | TIDAK_ALIH

        $senarai = FixedAsset::where('status', '!=', 'DIPADAM')
            ->when(in_array($jenis, ['ALIH', 'TIDAK_ALIH'], true), fn ($q) => $q->where('jenis_aset', $jenis))
            ->orderBy('tarikh_perolehan')
            ->orderBy('id')
            ->get();

        $namaCoa = Coa::whereIn('id', $senarai->pluck('coa_id')->filter()->unique())
            ->get(['id', 'kod', 'nama'])
            ->keyBy('id');

        return view('aset.senarai', compact('senarai', 'namaCoa', 'jenis'));
    }

    /** Butiran Aset (baca sahaja) — semua peranan; auto skop masjid via BelongsToMasjid. */
    public function lihat(FixedAsset $aset): View
    {
        $coaAset = $aset->coa_id ? Coa::find($aset->coa_id) : null;
        $coaSnt  = $aset->snt_coa_id ? Coa::find($aset->snt_coa_id) : null;

        $sejarah = DepreciationSchedule::where('fixed_asset_id', $aset->id)
            ->orderByDesc('tahun')->orderByDesc('bulan')->orderByDesc('id')
            ->get();

        $nilaiBersih = (float) $aset->kos - (float) $aset->accumulated_depn;

        return view('aset.lihat', compact('aset', 'coaAset', 'coaSnt', 'sejarah', 'nilaiBersih'));
    }
}
