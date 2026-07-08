<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;

/**
 * Pagar peranan BACA-SAHAJA (deny-by-default) merentas seluruh kumpulan auth+masjid:
 *  • Pemerhati (viewer — JAWI/MAIWP pantau masjid seliaan) = LAPORAN SAHAJA:
 *    penyata + laporan perakaunan + statistik (baca); halaman lain dialih ke
 *    penyata bulanan, semua TULIS → 403.
 *  • Juruaudit = BACA penuh (tiada alih), tetapi semua TULIS → 403 walaupun route tiada
 *    gate role: (pagar bakap supaya sebarang POST baharu tidak sengaja boleh ditulis
 *    juruaudit — selari dgn sekatan tulis RoleMiddleware yang hanya berfungsi pada route
 *    ber-gate role:).
 * Laluan akaun-sendiri (tukar kata laluan, tukar masjid, logout, bahasa) dibenarkan.
 */
class RestrictViewer
{
    /** Laluan yang DIBENARKAN untuk pemerhati (semua BACA sahaja). */
    private const DIBENARKAN = [
        // Penyata (+ cetak/PDF — route sama, ?format=pdf)
        'penyata.bulanan', 'penyata.bank', 'penyata.tahunan',
        // Laporan perakaunan penuh (keputusan 8 Jul 2026 — pemantauan JAWI/MAIWP)
        'akaun.untungrugi', 'akaun.kunci', 'akaun.imbangan',
        'akaun.lejer', 'akaun.lejerakaun', 'akaun.program',
        // Statistik (baca)
        'statistik.kutipan', 'statistik.kutipan_coa', 'statistik.belanja',
        'statistik.belanja_coa', 'statistik.jumaat',
        // Akaun sendiri
        'masjid.tukar', 'logout', 'bahasa',
        'tetapan.katalaluan', 'tetapan.katalaluan.kemaskini',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $role = $user?->role;
        $route = $request->route()?->getName();
        $dibenarkan = in_array($route, self::DIBENARKAN, true); // penyata.bulanan termasuk → tiada gelung

        // Pemerhati: tulis → 403 (selari RoleMiddleware); baca bukan-penyata → alih ke penyata.
        if ($role === UserRole::VIEWER && ! $dibenarkan) {
            if (! $request->isMethodSafe()) {
                abort(403, 'Akses baca sahaja (pemerhati).');
            }

            return redirect()->route('penyata.bulanan');
        }

        // Juruaudit: baca penuh dibenarkan; semua TULIS disekat di peringkat kumpulan
        // (deny-by-default), kecuali laluan akaun-sendiri dalam DIBENARKAN.
        if ($role === UserRole::JURUAUDIT && ! $dibenarkan && ! $request->isMethodSafe()) {
            abort(403, 'Akses baca sahaja (juruaudit).');
        }

        return $next($request);
    }
}
