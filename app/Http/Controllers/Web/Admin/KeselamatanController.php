<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUser;
use App\Models\LoginAttempt;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Keselamatan (Fasa 7) — dua tab: security_event & login_attempt.
 */
class KeselamatanController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->string('tab', 'event')->toString();

        // termasuk event peringkat sistem (masjid NULL, cth semakan integriti)
        $events = SecurityEvent::withoutMasjidScope()
            ->where(fn ($q) => $q->where('masjid_id', app('current.masjid_id'))->orWhereNull('masjid_id'))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'event_page')
            ->withQueryString();

        $percubaan = LoginAttempt::query()
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'login_page')
            ->withQueryString();

        $namaPengguna = AppUser::query()
            ->whereIn('id', $events->getCollection()->pluck('user_id')->filter()->unique())
            ->pluck('nama_penuh', 'id');

        return view('admin.keselamatan', compact('tab', 'events', 'percubaan', 'namaPengguna'));
    }
}
