<?php

namespace App\Http\Controllers\Web\Tetapan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tetapan\MasjidRequest;
use App\Models\Masjid;
use App\Services\Security\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Info Masjid (replika paparMasjid.php) — papar profil; edit oleh admin sahaja. */
class MasjidController extends Controller
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function index(): View
    {
        return view('tetapan.masjid', [
            'masjid'   => Masjid::findOrFail((int) app('current.masjid_id')),
            'kategori' => \App\Http\Requests\Tetapan\MasjidRequest::KATEGORI,
        ]);
    }

    public function kemaskini(MasjidRequest $request): RedirectResponse
    {
        $masjid = Masjid::findOrFail((int) app('current.masjid_id'));

        $sebelum = $masjid->only(array_keys($request->validated()));
        $masjid->update($request->validated());
        $this->audit->log('UPDATE', 'masjid', $sebelum, $request->validated(), $masjid->id);
        Masjid::lupakanSemasa(); // butiran baharu terus tampak di semua view

        return redirect()->route('tetapan.masjid')->with('success', 'Info masjid berjaya dikemaskini');
    }
}
