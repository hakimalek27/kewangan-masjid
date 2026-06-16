<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Log ralat aplikasi (Fasa 7) — diisi automatik oleh handler ralat global
 * (bootstrap/app.php) dan job backup; pentadbir menanda 'selesai'.
 */
class RalatController extends Controller
{
    public function index(Request $request): View
    {
        // withoutMasjidScope + tapis manual: ralat konsol/sistem (masjid NULL)
        // turut dipaparkan kepada pentadbir masjid
        $ralat = ErrorLog::withoutMasjidScope()
            ->where(fn ($q) => $q->where('masjid_id', app('current.masjid_id'))->orWhereNull('masjid_id'))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->string('level')))
            ->when($request->string('papar')->toString() !== 'semua', fn ($q) => $q->where('resolved', 0))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.ralat', ['ralat' => $ralat]);
    }

    public function selesai(int $ralat): RedirectResponse
    {
        $log = ErrorLog::withoutMasjidScope()
            ->where(fn ($q) => $q->where('masjid_id', app('current.masjid_id'))->orWhereNull('masjid_id'))
            ->findOrFail($ralat);

        $log->update(['resolved' => 1]);

        return back()->with('success', "Ralat #{$log->id} ditanda selesai.");
    }
}
