<?php

namespace App\Http\Controllers\Web\Lanjutan;

use App\Http\Controllers\Controller;
use App\Models\Coa;
use App\Models\FundAccount;
use App\Services\Lanjutan\FundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fasa 9 — Dana & Tabung: CRUD fund_account (COA 300-04xxx) + baki setiap dana
 * + transaksi dana + amaran defisit (kad merah).
 */
class DanaController extends Controller
{
    public function __construct(private FundService $servis)
    {
    }

    public function index(Request $request): View
    {
        $this->servis->seedLalai(); // pastikan 300-04010..04050 wujud

        $senarai = $this->servis->senarai();
        $coaId = (int) $request->input('coa_id') ?: null;
        $pilihan = $coaId ? $senarai->firstWhere('coa_id', $coaId) : null;

        return view('lanjutan.dana', [
            'senarai'   => $senarai,
            'defisit'   => $senarai->where('defisit', true)->values(),
            'pilihan'   => $pilihan,
            'transaksi' => $pilihan ? $this->servis->transaksi((int) $pilihan->coa_id) : collect(),
            'coaTabung' => Coa::query()->postable()
                ->where('kod', 'like', '300-04%')
                ->whereNotIn('id', $senarai->pluck('coa_id'))
                ->orderBy('kod')->get(['id', 'kod', 'nama']),
        ]);
    }

    /**
     * Semakan masa-nyata untuk borang Perbelanjaan (komponen x-fund-warning):
     * jika COA dipilih ialah dana 300-04xxx, kira baki selepas bayaran &
     * amaran jika menjadi defisit (allow_deficit dihormati).
     */
    public function semak(Request $request): \Illuminate\Http\JsonResponse
    {
        if (\App\Support\Setting::get('fund_deficit_alert', 'on') === 'off') {
            return response()->json(['dana' => false]);
        }

        $coaId = (int) $request->input('coa_id');
        $jumlah = (float) $request->input('jumlah', 0);
        if ($coaId <= 0 || $jumlah <= 0) {
            return response()->json(['dana' => false]);
        }

        $this->servis->seedLalai(); // pastikan dana lalai 300-04xxx wujud
        $dana = $this->servis->senarai()->firstWhere('coa_id', $coaId);
        if (!$dana || $dana->allow_deficit) {
            return response()->json(['dana' => false]);
        }

        $bakiSelepas = round((float) $dana->baki - $jumlah, 2);

        return response()->json([
            'dana'            => true,
            'nama'            => $dana->nama,
            'baki'            => number_format((float) $dana->baki, 2, '.', ''),
            'baki_selepas'    => number_format($bakiSelepas, 2, '.', ''),
            'defisit_selepas' => $bakiSelepas < 0,
        ]);
    }

    /** Tambah / kemaskini dana (admin & bendahari). */
    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id'            => ['nullable', 'integer'],
            'coa_id'        => ['required_without:id', 'nullable', 'integer', 'exists:coa,id'],
            'nama'          => ['required', 'string', 'max:150'],
            'allow_deficit' => ['nullable', 'boolean'],
        ], [], ['coa_id' => 'Akaun Tabung (COA)', 'nama' => 'Nama Dana']);

        if (!empty($data['id'])) {
            $fund = FundAccount::findOrFail((int) $data['id']);
            $fund->update([
                'nama'          => $data['nama'],
                'allow_deficit' => (int) !empty($data['allow_deficit']),
            ]);
        } else {
            $coa = Coa::findOrFail((int) $data['coa_id']);
            abort_unless(str_starts_with($coa->kod, '300-04'), 422, 'Dana mesti merujuk COA tabung 300-04xxx.');

            FundAccount::firstOrCreate(
                ['coa_id' => $coa->id],
                ['nama' => $data['nama'], 'allow_deficit' => (int) !empty($data['allow_deficit'])],
            )->update([
                'nama'          => $data['nama'],
                'allow_deficit' => (int) !empty($data['allow_deficit']),
            ]);
        }

        return redirect()->route('dana.index')->with('success', 'Dana/tabung berjaya disimpan.');
    }
}
