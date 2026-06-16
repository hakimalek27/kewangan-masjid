<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ikat masjid AKTIF pengguna semasa ke container — digunakan oleh trait
 * BelongsToMasjid (skop global) supaya semua query tertapis kepada masjid itu.
 *
 * Masjid aktif = pilihan sesi ('selected_masjid_id') JIKA dalam senarai masjid
 * yang boleh dicapai pengguna (admin: semua; pemerhati: rumah + ditugaskan);
 * jika tidak → masjid rumah (atau masjid pertama yang boleh dicapai). Ini titik
 * tunggal — penukar masjid (MasjidSwitchController) hanya menulis sesi, skop
 * global mengikut secara automatik. Bendahari (dll) sentiasa terkunci ke rumah.
 */
class SetMasjidContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            app()->instance('current.user_id', (int) $user->id);

            $boleh = $user->accessibleMasjidIds();
            $home = (int) $user->masjid_id;
            $dipilih = (int) $request->session()->get('selected_masjid_id', 0);

            $aktif = in_array($dipilih, $boleh, true)
                ? $dipilih
                : (in_array($home, $boleh, true) ? $home : (int) ($boleh[0] ?? $home));

            app()->instance('current.masjid_id', $aktif);
        }

        return $next($request);
    }
}
