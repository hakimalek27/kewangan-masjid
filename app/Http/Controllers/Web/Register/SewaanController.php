<?php

namespace App\Http\Controllers\Web\Register;

use App\Http\Controllers\Controller;
use App\Http\Requests\Register\SewaanRequest;
use App\Models\Sewaan;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Register Sewaan — tiada GL (replika daftarSewaan.php / listingSewa.php / editSewaan.php). */
class SewaanController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function daftar(): View
    {
        return view('register.sewa-daftar');
    }

    public function simpan(SewaanRequest $request): RedirectResponse
    {
        $sewa = Sewaan::create([...$request->validated(), 'status' => 'AKTIF']);
        $this->audit->log('CREATE', 'sewaan', null, $request->validated(), $sewa->id);

        return redirect()
            ->route('sewa.senarai')
            ->with('success', 'Sewaan berjaya didaftarkan');
    }

    public function senarai(Request $request): View
    {
        $tahun = (int) $request->input('y', now()->year);

        $senarai = Sewaan::where('status', '!=', 'DIPADAM')
            ->whereYear('created_at', $tahun)
            ->orderBy('id')
            ->get();

        return view('register.sewa-senarai', compact('senarai', 'tahun'));
    }

    public function edit(Sewaan $sewaan): View
    {
        return view('register.sewa-edit', compact('sewaan'));
    }

    public function kemaskini(SewaanRequest $request, Sewaan $sewaan): RedirectResponse
    {
        $sebelum = $sewaan->only(array_keys($request->validated()));
        $sewaan->update($request->validated());
        $this->audit->log('UPDATE', 'sewaan', $sebelum, $request->validated(), $sewaan->id);

        return redirect()
            ->route('sewa.senarai')
            ->with('success', 'Sewaan berjaya dikemaskini');
    }

    /** "Padam Rekod" = status DIPADAM (bukan hard delete — jejak audit kekal). */
    public function padam(Sewaan $sewaan): RedirectResponse
    {
        $sewaan->update(['status' => 'DIPADAM']);
        $this->audit->log('DELETE', 'sewaan', ['nama_penyewa' => $sewaan->nama_penyewa], null, $sewaan->id);

        return redirect()
            ->route('sewa.senarai')
            ->with('success', 'Rekod sewaan berjaya dipadam');
    }
}
