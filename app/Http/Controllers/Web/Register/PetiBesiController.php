<?php

namespace App\Http\Controllers\Web\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\Register\PetiBesiRequest;
use App\Models\PetiBesi;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Register Peti Besi — tiada GL (replika daftarPetiBesi.php / listingPetiBesi.php). */
class PetiBesiController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function daftar(): View
    {
        return view('register.petibesi-daftar');
    }

    public function simpan(PetiBesiRequest $request): RedirectResponse
    {
        $rekod = PetiBesi::create($request->validated());
        $this->audit->log('CREATE', 'peti_besi', null, $request->validated(), $rekod->id);

        return redirect()
            ->route('petibesi.senarai')
            ->with('success', 'Rekod peti besi berjaya didaftarkan');
    }

    public function senarai(Request $request): View
    {
        $bulan = (int) $request->input('m', now()->month);
        $tahun = (int) $request->input('y', now()->year);

        $senarai = PetiBesi::whereYear('tarikh_masuk', $tahun)
            ->whereMonth('tarikh_masuk', $bulan)
            ->orderBy('tarikh_masuk')
            ->orderBy('id')
            ->get();

        return view('register.petibesi-senarai', compact('senarai', 'bulan', 'tahun'));
    }
}
