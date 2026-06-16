<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\MappingRequest;
use App\Models\CoaLocalMapping;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Set Kod Penerimaan/Perbelanjaan (replika setup_mapping.php) — pemetaan
 * label tempatan masjid → COA. Padam dibenarkan (bukan rekod kewangan).
 */
class MappingController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        $senarai = CoaLocalMapping::query()
            ->join('coa', 'coa.id', '=', 'coa_local_mapping.coa_id')
            ->orderBy('coa_local_mapping.local_label')
            ->get(['coa_local_mapping.*', 'coa.kod as coa_kod', 'coa.nama as coa_nama']);

        return view('tetapan.mapping-index', compact('senarai'));
    }

    public function simpan(MappingRequest $request): RedirectResponse
    {
        $mapping = CoaLocalMapping::create($request->validated());
        $this->audit->log('CREATE', 'coa_local_mapping', null, $request->validated(), $mapping->id);

        return redirect()->route('tetapan.mapping')->with('success', 'Pemetaan kod berjaya disimpan');
    }

    public function edit(CoaLocalMapping $mapping): View
    {
        return view('tetapan.mapping-edit', compact('mapping'));
    }

    public function kemaskini(MappingRequest $request, CoaLocalMapping $mapping): RedirectResponse
    {
        $sebelum = $mapping->only(array_keys($request->validated()));
        $mapping->update($request->validated());
        $this->audit->log('UPDATE', 'coa_local_mapping', $sebelum, $request->validated(), $mapping->id);

        return redirect()->route('tetapan.mapping')->with('success', 'Pemetaan kod berjaya dikemaskini');
    }

    /** Padam sebenar dibenarkan — pemetaan bukan rekod kewangan (jejak audit kekal). */
    public function padam(CoaLocalMapping $mapping): RedirectResponse
    {
        $sebelum = $mapping->only(['coa_id', 'local_label', 'jenis_guna']);
        $mapping->delete();
        $this->audit->log('DELETE', 'coa_local_mapping', $sebelum, null, $mapping->id);

        return redirect()->route('tetapan.mapping')->with('success', 'Pemetaan kod berjaya dipadam');
    }
}
