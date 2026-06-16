<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\PenyataModRequest;
use App\Http\Requests\Tetapan\SignatureRequest;
use App\Models\PenyataSetting;
use App\Models\PenyataSignature;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Setting Penyata (replika penyata_settings.php) — senarai tandatangan
 * (nama/jawatan/susunan/aktif) + mod paparan SIGNATURE atau DISCLAIMER.
 */
class PenyataSettingController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        return view('tetapan.penyata-setting', [
            'tandatangan' => PenyataSignature::orderBy('susunan')->orderBy('id')->get(),
            'setting'     => PenyataSetting::first(),
        ]);
    }

    public function sigSimpan(SignatureRequest $request): RedirectResponse
    {
        $data = [...$request->validated(), 'aktif' => $request->boolean('aktif')];
        $sig = PenyataSignature::create($data);
        $this->audit->log('CREATE', 'penyata_signature', null, $data, $sig->id);

        return redirect()->route('penyata.setting')->with('success', 'Tandatangan berjaya ditambah');
    }

    public function sigEdit(PenyataSignature $signature): View
    {
        return view('tetapan.penyata-sig-edit', compact('signature'));
    }

    public function sigKemaskini(SignatureRequest $request, PenyataSignature $signature): RedirectResponse
    {
        $data = [...$request->validated(), 'aktif' => $request->boolean('aktif')];
        $sebelum = $signature->only(array_keys($data));
        $signature->update($data);
        $this->audit->log('UPDATE', 'penyata_signature', $sebelum, $data, $signature->id);

        return redirect()->route('penyata.setting')->with('success', 'Tandatangan berjaya dikemaskini');
    }

    public function sigPadam(PenyataSignature $signature): RedirectResponse
    {
        $sebelum = $signature->only(['nama', 'jawatan', 'susunan']);
        $signature->delete();
        $this->audit->log('DELETE', 'penyata_signature', $sebelum, null, $signature->id);

        return redirect()->route('penyata.setting')->with('success', 'Tandatangan berjaya dipadam');
    }

    /** Mod paparan penyata — tandatangan atau teks disclaimer. */
    public function mod(PenyataModRequest $request): RedirectResponse
    {
        $masjidId = (int) app('current.masjid_id');
        $sebelum = PenyataSetting::first()?->only(['mode', 'disclaimer_text']);

        PenyataSetting::updateOrCreate(
            ['masjid_id' => $masjidId],
            [
                'mode'            => $request->input('mode'),
                'disclaimer_text' => $request->input('disclaimer_text'),
            ],
        );

        $this->audit->log('UPDATE', 'penyata_setting', $sebelum,
            $request->only(['mode', 'disclaimer_text']), $masjidId);

        return redirect()->route('penyata.setting')->with('success', 'Pilihan paparan penyata berjaya disimpan');
    }
}
