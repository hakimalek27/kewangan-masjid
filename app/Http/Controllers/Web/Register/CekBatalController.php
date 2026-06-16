<?php

namespace App\Http\Controllers\Web\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\Register\CekBatalRequest;
use App\Models\CekBatal;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Register Cek Batal — tiada GL (replika daftarBukuCekBatal.php / listingBukuCekBatal.php). */
class CekBatalController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function daftar(): View
    {
        return view('register.cekbatal-daftar');
    }

    public function simpan(CekBatalRequest $request): RedirectResponse
    {
        $cek = CekBatal::create($request->validated());
        $this->audit->log('CREATE', 'cek_batal', null, $request->validated(), $cek->id);

        return redirect()
            ->route('cekbatal.senarai')
            ->with('success', 'Cek batal berjaya didaftarkan');
    }

    public function senarai(Request $request): View
    {
        $bulan = (int) $request->input('m', now()->month);
        $tahun = (int) $request->input('y', now()->year);

        $senarai = CekBatal::whereYear('tarikh_batal', $tahun)
            ->whereMonth('tarikh_batal', $bulan)
            ->orderBy('tarikh_batal')
            ->orderBy('id')
            ->get();

        return view('register.cekbatal-senarai', compact('senarai', 'bulan', 'tahun'));
    }
}
