<?php

namespace App\Http\Controllers\Web\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\Register\BukuCekRequest;
use App\Models\BukuCek;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Register Buku Cek — tiada GL (replika daftarBukuCek.php / listingBukuCek.php). */
class BukuCekController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function daftar(): View
    {
        return view('register.cek-daftar');
    }

    public function simpan(BukuCekRequest $request): RedirectResponse
    {
        $cek = BukuCek::create($request->validated());
        $this->audit->log('CREATE', 'buku_cek', null, $request->validated(), $cek->id);

        return redirect()
            ->route('cek.senarai')
            ->with('success', 'Buku cek berjaya didaftarkan');
    }

    public function senarai(Request $request): View
    {
        $tahun = (int) $request->input('y', now()->year);

        $senarai = BukuCek::whereYear('tarikh_keluar', $tahun)
            ->orderBy('tarikh_keluar')
            ->orderBy('id')
            ->get();

        $namaBank = \App\Models\BankAccount::whereIn('id', $senarai->pluck('bank_account_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return view('register.cek-senarai', compact('senarai', 'tahun', 'namaBank'));
    }
}
