<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Masjid;
use App\Services\Security\SecurityEventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Penukar masjid aktif (admin: semua; pemerhati: masjid ditugaskan). Hanya menulis
 * pilihan ke sesi — SetMasjidContext membaca sesi dan skop global BelongsToMasjid
 * mengikut secara automatik. Sasaran mesti dalam senarai boleh-capai pengguna.
 */
class MasjidSwitchController extends Controller
{
    public function tukar(Request $request, SecurityEventService $security): RedirectResponse
    {
        $target = (int) $request->input('masjid_id');
        $user = $request->user();

        if (! $user || ! $user->canAccessMasjid($target)) {
            $security->log('PERMISSION_DENIED',
                'Cuba tukar ke masjid #'.$target.' tanpa kebenaran (user '.($user?->id ?? '?').')', 'MEDIUM');
            abort(403, 'Anda tiada kebenaran untuk masjid tersebut.');
        }

        $request->session()->put('selected_masjid_id', $target);
        Masjid::lupakanSemasa();

        // Konsol Sistem (admin) hantar ke=dashboard supaya "Masuk" mendarat di dashboard masjid.
        return $request->input('ke') === 'dashboard'
            ? redirect()->route('dashboard')->with('success', 'Masjid aktif ditukar.')
            : redirect()->back()->with('success', 'Masjid aktif ditukar.');
    }
}
