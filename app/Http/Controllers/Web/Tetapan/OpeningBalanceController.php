<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\OpeningBalanceRequest;
use App\Models\Coa;
use App\Models\OpeningBalance;
use App\Services\Accounting\OpeningBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Set Baki Awal (replika opening_balance.php) — jurnal OB-<tahun>
 * Dr Bank/Aset, Cr 100-10000 Dana Terkumpul. Selepas "Muktamad & Kunci"
 * sistem terkunci; "Reset & Edit Semula" membatalkan voucher & membuka kunci.
 */
class OpeningBalanceController extends Controller
{
    public function __construct(private OpeningBalanceService $opening)
    {
    }

    public function index(Request $request): View
    {
        $tahun = (int) $request->input('tahun', now()->year);

        $baris = OpeningBalance::where('tahun', $tahun)->orderBy('id')->get()
            ->each(fn ($b) => $b->setRelation('coa', Coa::find($b->coa_id)));

        // COA dibenarkan: Aset Tetap (200), Aset Semasa (250), Liabiliti (300) — bukan header
        $coaSenarai = Coa::postable()
            ->where(fn ($q) => $q
                ->orWhere('kod', 'like', '200%')
                ->orWhere('kod', 'like', '250%')
                ->orWhere('kod', 'like', '300%'))
            ->orderBy('kod')
            ->get(['id', 'kod', 'nama']);

        return view('tetapan.opening', [
            'tahun'      => $tahun,
            'baris'      => $baris,
            'isLocked'   => $baris->contains(fn ($b) => $b->is_locked),
            'coaSenarai' => $coaSenarai,
        ]);
    }

    public function simpan(OpeningBalanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->opening->set((int) $data['tahun'], $data['rows']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['rows' => $e->getMessage()])->withInput();
        }

        return redirect()->route('bank.opening', ['tahun' => $data['tahun']])
            ->with('success', "Baki awal {$data['tahun']} berjaya disimpan (jurnal OB-{$data['tahun']} dijana)");
    }

    public function kunci(Request $request): RedirectResponse
    {
        $tahun = (int) $request->input('tahun');

        if (!OpeningBalance::where('tahun', $tahun)->exists()) {
            return back()->withErrors(['tahun' => "Tiada baki awal {$tahun} untuk dimuktamadkan. Sila simpan dahulu."]);
        }

        $this->opening->lock($tahun);

        return redirect()->route('bank.opening', ['tahun' => $tahun])
            ->with('success', "Baki awal {$tahun} dimuktamadkan dan dikunci");
    }

    /** "Reset & Edit Semula" — batal voucher OB & buka kunci. */
    public function reset(Request $request): RedirectResponse
    {
        $tahun = (int) $request->input('tahun');
        $this->opening->resetAndUnlock($tahun);

        return redirect()->route('bank.opening', ['tahun' => $tahun])
            ->with('success', "Baki awal {$tahun} direset — jurnal OB dibatalkan dan kunci dibuka");
    }
}
