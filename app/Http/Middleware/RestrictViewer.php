<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

/**
 * Pemerhati (viewer) = PENYATA SAHAJA. RoleMiddleware sudah sekat semua TULIS untuk
 * viewer; middleware ini pula mengehadkan halaman BACA viewer kepada penyata bulanan/
 * tahunan + beberapa laluan akaun-sendiri. Ini pagar menyeluruh (deny-by-default) supaya
 * tiada halaman lain terdedah secara tidak sengaja walaupun dipaut di mana-mana.
 */
class RestrictViewer
{
    /** Laluan yang DIBENARKAN untuk pemerhati. */
    private const DIBENARKAN = [
        'penyata.bulanan', 'penyata.bank', 'penyata.tahunan', // penyata + cetak/PDF (route sama, ?format=pdf)
        'masjid.tukar', 'logout', 'bahasa',
        'tetapan.katalaluan', 'tetapan.katalaluan.kemaskini', // tukar kata laluan sendiri
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role === UserRole::VIEWER) {
            $route = $request->route()?->getName();
            if (! in_array($route, self::DIBENARKAN, true)) { // penyata.bulanan termasuk dlm DIBENARKAN → tiada gelung
                // Tulis (POST/PUT/DELETE) → 403 (selari RoleMiddleware); baca (GET) → alih ke penyata.
                if (! $request->isMethodSafe()) {
                    abort(403, 'Akses baca sahaja (pemerhati).');
                }

                return redirect()->route('penyata.bulanan');
            }
        }

        return $next($request);
    }
}
