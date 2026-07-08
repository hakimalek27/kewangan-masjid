<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityEventService;
use Closure;
use Illuminate\Http\Request;

/**
 * Sekat laluan ikut peranan: Route::middleware('role:admin,bendahari').
 * Juruaudit/viewer = baca sahaja (sekat semua kaedah tulis secara automatik).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $role = $user->role->value;

        // Superadmin (admin) = PENYEDIA (provider). Boleh BACA semua tenant
        // (lihat/audit + tukar-masjid) dan urus fungsi peringkat-SISTEM (route
        // yang secara eksplisit izinkan 'admin': konsol, /admin/*, onboarding,
        // tetapan API/AI, urus pengguna). TETAPI TIDAK menyentuh kewangan/tetapan
        // tenant — tulis (kaedah tidak-selamat) melalui pagar bukan-admin DIHALANG
        // (pengasingan penyedia-vs-penyewa; keputusan reka bentuk 8 Jul 2026).
        if ($role === \App\Enums\UserRole::ADMIN->value) {
            if ($request->isMethodSafe() || in_array('admin', $roles, true)) {
                return $next($request);
            }

            app(SecurityEventService::class)->log(
                'PERMISSION_DENIED',
                'Superadmin (provider) cuba menulis melalui pagar ['.implode(',', $roles).'] di '.$request->path(),
                'MEDIUM'
            );
            abort(403, 'Superadmin (penyedia) tidak menyentuh kewangan/tetapan tenant.');
        }

        if (!in_array($role, $roles, true)) {
            app(SecurityEventService::class)->log(
                'PERMISSION_DENIED',
                "Peranan {$role} cuba akses ".$request->path(),
                'MEDIUM'
            );
            abort(403, 'Anda tiada kebenaran untuk halaman ini.');
        }

        // Peranan baca-sahaja tidak boleh menulis walau lulus senarai peranan
        if (in_array($role, ['juruaudit', 'viewer'], true) && !$request->isMethodSafe()) {
            abort(403, 'Akses baca sahaja.');
        }

        return $next($request);
    }
}
