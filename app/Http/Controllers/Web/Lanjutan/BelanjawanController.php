<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Services\Lanjutan\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fasa 9 — Belanjawan: peruntukan tahunan per COA Belanja + laporan varians
 * (peruntukan vs sebenar, bar %, merah jika lebih) + endpoint semakan JSON
 * untuk amaran pada borang Perbelanjaan (komponen x-budget-warning).
 */
class BelanjawanController extends Controller
{
    public function __construct(private BudgetService $servis)
    {
    }

    public function index(Request $request): View
    {
        $tahun = (int) $request->input('tahun', now()->year);

        return view('lanjutan.belanjawan', [
            'tahun'   => $tahun,
            'varians' => $this->servis->varians($tahun),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun'          => ['required', 'integer', 'between:2000,2100'],
            'peruntukan'     => ['required', 'array'],
            'peruntukan.*'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $bil = $this->servis->simpan((int) $data['tahun'], array_filter(
            $data['peruntukan'],
            fn ($v) => $v !== null && $v !== '',
        ));

        return redirect()->route('belanjawan.index', ['tahun' => $data['tahun']])
            ->with('success', "Belanjawan {$data['tahun']} disimpan ($bil akaun berperuntukan).");
    }

    /** GET /belanjawan/semak?coa_id=&jumlah= → JSON {melebihi, peruntukan, sebenar, baki}. */
    public function semak(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coa_id' => ['required', 'integer'],
            'jumlah' => ['required', 'numeric', 'min:0'],
            'tahun'  => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        return response()->json($this->servis->semak(
            (int) $data['coa_id'],
            (float) $data['jumlah'],
            isset($data['tahun']) ? (int) $data['tahun'] : null,
        ));
    }
}
